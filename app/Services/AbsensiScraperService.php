<?php

namespace App\Services;

use App\Models\AbsensiCutiReport;
use App\Models\AbsensiDailyReport;
use App\Models\AbsensiPegawai;
use App\Models\AbsensiPppk;
use App\Models\AbsensiPppkReport;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DomCrawler\Crawler;
use Throwable;

class AbsensiScraperService
{
    private const DEFAULT_BASE_URL = 'https://absensi.banjarmasinkota.go.id';
    private const DEFAULT_COOKIE_SESSION_KEY = 'absensi_scraper.cookies';
    private const DEFAULT_SKPD_ID = 1;
    private const ADMIN_CUTI_PATH = '/admin/cuti';
    private const ADMIN_LAPORAN_PATH = '/admin/laporan';
    private const ADMIN_LAPORAN_TANGGAL_PATH = '/admin/laporan/tanggal';
    private const ADMIN_PPPK_PATH = '/admin/pppk';
    private const SUPERADMIN_PEGAWAI_PATH = '/superadmin/pegawai';
    private const SENSITIVE_HEADER_KEYWORDS = ['nip', 'nama', 'pegawai', 'nik', 'alamat', 'telepon', 'hp', 'email'];

    private Client $client;
    private CookieJar $cookieJar;
    private string $baseUrl;
    private string $cookieSessionKey;

    public function __construct(?Client $client = null)
    {
        $this->baseUrl = rtrim((string) $this->configValue('services.absensi.base_url', env('ABSENSI_BASE_URL', self::DEFAULT_BASE_URL)), '/');
        $this->cookieSessionKey = (string) $this->configValue('services.absensi.cookie_session_key', env('ABSENSI_COOKIE_SESSION_KEY', self::DEFAULT_COOKIE_SESSION_KEY));

        $this->cookieJar = CookieJar::fromArray([], $this->cookieHost());
        $this->restoreCookiesFromLaravelSession();

        $stack = HandlerStack::create();
        $stack->push($this->requestLogger());

        $this->client = $client ?: new Client([
            'base_uri' => $this->baseUrl,
            'cookies' => $this->cookieJar,
            'handler' => $stack,
            'http_errors' => false,
            'allow_redirects' => [
                'max' => 10,
                'track_redirects' => true,
                'referer' => true,
            ],
            'timeout' => 30,
            'connect_timeout' => 15,
            'headers' => $this->defaultHeaders(),
        ]);
    }

    public function login(string $username, string $password, int $skpdId = self::DEFAULT_SKPD_ID): array
    {
        $this->resetPortalSession();
        $this->resetPortalSession();
        $auth = $this->authenticatePortal($username, $password);
        $response = $auth['response'];
        $body = $auth['body'];
        $skpdLogin = $this->loginAsSkpd($skpdId);
        $cutiPage = $this->getCutiPage();
        $success = (bool) ($cutiPage['success'] ?? false);

        $this->syncCookiesToLaravelSession();

        return [
            'success' => $success,
            'login' => [
                'status_code' => $response->getStatusCode(),
                'redirect_history' => $this->redirectHistory($response),
                'body_preview' => $this->preview($body),
            ],
            'skpd_login' => $skpdLogin,
            'cuti_page' => $cutiPage,
            'cookies_count' => count($this->cookieJar->toArray()),
        ];
    }

    public function scrapeCuti(string $username, string $password, bool $redact = true, int $skpdId = self::DEFAULT_SKPD_ID): array
    {
        $login = $this->login($username, $password, $skpdId);

        if (! ($login['success'] ?? false)) {
            return [
                'success' => false,
                'message' => 'Login Absensi gagal atau halaman cuti tidak bisa diakses.',
                'login' => $login,
            ];
        }

        return $this->getCutiData($redact, $skpdId);
    }

    public function getCutiData(bool $redact = true, int $skpdId = self::DEFAULT_SKPD_ID): array
    {
        return $this->getCutiDataForRange($redact, $skpdId);
    }

    public function scrapeAllSkpdCuti(
        string $username,
        string $password,
        bool $redact = false,
        ?string $dateStart = null,
        ?string $dateEnd = null,
        int $startSkpdId = 1,
        int $endSkpdId = 35
    ): array {
        $auth = $this->authenticatePortal($username, $password);
        $results = [];
        $stored = 0;
        $successCount = 0;
        $failedCount = 0;
        $startSkpdId = max(1, $startSkpdId);
        $endSkpdId = max($startSkpdId, $endSkpdId);
        $skpdActions = $this->fetchSkpdLoginActions($startSkpdId, $endSkpdId);

        for ($skpdId = $startSkpdId; $skpdId <= $endSkpdId; $skpdId++) {
            try {
                if ($skpdId > $startSkpdId) {
                    $this->resetPortalSession();
                    $this->authenticatePortal($username, $password);
                }

                $skpdLogin = $this->loginAsSkpd($skpdId, $skpdActions[$skpdId] ?? null);
                $cuti = $this->getCutiDataForRange($redact, $skpdId, $dateStart, $dateEnd, true);
                $isSuccess = (bool) ($cuti['success'] ?? false);
                $storedForSkpd = (int) ($cuti['stored_rows'] ?? 0);

                $results[] = [
                    'skpd_id' => $skpdId,
                    'success' => $isSuccess,
                    'stored_rows' => $storedForSkpd,
                    'skpd_login' => [
                        'success' => $skpdLogin['success'] ?? false,
                        'path' => $skpdLogin['path'] ?? null,
                        'status_code' => $skpdLogin['status_code'] ?? null,
                        'action' => $skpdLogin['action'] ?? null,
                    ],
                    'page' => $cuti['page'] ?? null,
                    'message' => $cuti['message'] ?? null,
                ];

                $stored += $storedForSkpd;
                $isSuccess ? $successCount++ : $failedCount++;
            } catch (Throwable $throwable) {
                $failedCount++;
                $results[] = [
                    'skpd_id' => $skpdId,
                    'success' => false,
                    'stored_rows' => 0,
                    'message' => $throwable->getMessage(),
                ];

                Log::error('Absensi all SKPD cuti fetch failed', [
                    'skpd_id' => $skpdId,
                    'message' => $throwable->getMessage(),
                ]);
            }
        }

        return [
            'success' => $successCount > 0 && $failedCount === 0,
            'partial_success' => $successCount > 0 && $failedCount > 0,
            'login' => [
                'status_code' => $auth['response']->getStatusCode(),
                'redirect_history' => $this->redirectHistory($auth['response']),
                'body_preview' => $this->preview($auth['body']),
            ],
            'range' => [
                'date_start' => $dateStart,
                'date_end' => $dateEnd,
                'skpd_start' => $startSkpdId,
                'skpd_end' => $endSkpdId,
            ],
            'summary' => [
                'success_count' => $successCount,
                'failed_count' => $failedCount,
                'stored_rows' => $stored,
            ],
            'results' => $results,
        ];
    }

    public function scrapePegawai(string $username, string $password): array
    {
        $auth = $this->authenticatePortal($username, $password);
        $page = $this->getPegawaiPage();

        if (! ($page['success'] ?? false)) {
            return [
                'success' => false,
                'message' => 'Halaman pegawai tidak bisa diakses. Login terlebih dahulu.',
                'login' => [
                    'status_code' => $auth['response']->getStatusCode(),
                    'redirect_history' => $this->redirectHistory($auth['response']),
                    'body_preview' => $this->preview($auth['body']),
                ],
                'page' => $page,
            ];
        }

        $body = is_string($page['body'] ?? null) ? (string) $page['body'] : '';
        $parsed = $this->parsePegawaiHtml($body);
        $maxPage = $this->maxPaginationPage($body, self::SUPERADMIN_PEGAWAI_PATH);

        for ($pageNumber = 2; $pageNumber <= $maxPage; $pageNumber++) {
            $nextPage = $this->getPegawaiPage($pageNumber);
            if (! ($nextPage['success'] ?? false)) {
                break;
            }

            $nextBody = is_string($nextPage['body'] ?? null) ? (string) $nextPage['body'] : '';
            $parsed = $this->mergePegawaiData($parsed, $this->parsePegawaiHtml($nextBody));
        }

        $storedRows = $this->storePegawaiRows($parsed, [
            'fetched_at' => now(),
        ]);

        return [
            'success' => true,
            'login' => [
                'status_code' => $auth['response']->getStatusCode(),
                'redirect_history' => $this->redirectHistory($auth['response']),
                'body_preview' => $this->preview($auth['body']),
            ],
            'page' => [
                'path' => self::SUPERADMIN_PEGAWAI_PATH,
                'status_code' => $page['status_code'],
                'redirect_history' => $page['redirect_history'],
                'body_preview' => $page['body_preview'],
                'max_page' => $maxPage,
            ],
            'summary' => [
                'page_count' => $maxPage,
                'parsed_rows' => $parsed['row_count'] ?? 0,
                'stored_rows' => $storedRows,
            ],
        ];
    }

    public function scrapeDailyReports(
        string $username,
        string $password,
        string $dateStart,
        string $dateEnd,
        int $startSkpdId = 1,
        int $endSkpdId = 35
    ): array {
        $dateStart = $this->normalizeDateValue($dateStart) ?? now()->toDateString();
        $dateEnd = $this->normalizeDateValue($dateEnd) ?? $dateStart;
        $startDate = Carbon::parse($dateStart);
        $endDate = Carbon::parse($dateEnd);
        if ($endDate->lt($startDate)) {
            $endDate = $startDate->copy();
        }

        $this->resetPortalSession();
        $auth = $this->authenticatePortal($username, $password);
        $startSkpdId = max(1, $startSkpdId);
        $endSkpdId = max($startSkpdId, $endSkpdId);
        $skpdActions = $this->fetchSkpdLoginActions($startSkpdId, $endSkpdId);
        $results = [];
        $stored = 0;
        $successCount = 0;
        $failedCount = 0;

        for ($skpdId = $startSkpdId; $skpdId <= $endSkpdId; $skpdId++) {
            try {
                if ($skpdId > $startSkpdId) {
                    $this->resetPortalSession();
                    $this->authenticatePortal($username, $password);
                }

                $skpdLogin = $this->loginAsSkpd($skpdId, $skpdActions[$skpdId] ?? null);
                $storedForSkpd = 0;
                $dateResults = [];

                for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
                    $daily = $this->getDailyReportForDate($skpdId, $date->toDateString(), true);
                    $storedForDate = (int) ($daily['stored_rows'] ?? 0);
                    $storedForSkpd += $storedForDate;
                    $dateResults[] = [
                        'date' => $date->toDateString(),
                        'success' => (bool) ($daily['success'] ?? false),
                        'stored_rows' => $storedForDate,
                        'message' => $daily['message'] ?? null,
                    ];
                }

                $results[] = [
                    'skpd_id' => $skpdId,
                    'success' => true,
                    'stored_rows' => $storedForSkpd,
                    'skpd_login' => [
                        'success' => $skpdLogin['success'] ?? false,
                        'path' => $skpdLogin['path'] ?? null,
                        'status_code' => $skpdLogin['status_code'] ?? null,
                        'action' => $skpdLogin['action'] ?? null,
                    ],
                    'dates' => $dateResults,
                ];

                $stored += $storedForSkpd;
                $successCount++;
            } catch (Throwable $throwable) {
                $failedCount++;
                $results[] = [
                    'skpd_id' => $skpdId,
                    'success' => false,
                    'stored_rows' => 0,
                    'message' => $throwable->getMessage(),
                ];

                Log::error('Absensi daily report fetch failed', [
                    'skpd_id' => $skpdId,
                    'message' => $throwable->getMessage(),
                ]);
            }
        }

        return [
            'success' => $successCount > 0 && $failedCount === 0,
            'partial_success' => $successCount > 0 && $failedCount > 0,
            'login' => [
                'status_code' => $auth['response']->getStatusCode(),
                'redirect_history' => $this->redirectHistory($auth['response']),
                'body_preview' => $this->preview($auth['body']),
            ],
            'range' => [
                'date_start' => $startDate->toDateString(),
                'date_end' => $endDate->toDateString(),
                'skpd_start' => $startSkpdId,
                'skpd_end' => $endSkpdId,
            ],
            'summary' => [
                'success_count' => $successCount,
                'failed_count' => $failedCount,
                'stored_rows' => $stored,
            ],
            'results' => $results,
        ];
    }

    public function scrapePppkReports(
        string $username,
        string $password,
        string $dateStart,
        string $dateEnd,
        int $startSkpdId = 1,
        int $endSkpdId = 35
    ): array {
        $dateStart = $this->normalizeDateValue($dateStart) ?? now()->toDateString();
        $dateEnd = $this->normalizeDateValue($dateEnd) ?? $dateStart;
        $startDate = Carbon::parse($dateStart);
        $endDate = Carbon::parse($dateEnd);
        if ($endDate->lt($startDate)) {
            $endDate = $startDate->copy();
        }

        $this->resetPortalSession();
        $auth = $this->authenticatePortal($username, $password);
        $startSkpdId = max(1, $startSkpdId);
        $endSkpdId = max($startSkpdId, $endSkpdId);
        $skpdActions = $this->fetchSkpdLoginActions($startSkpdId, $endSkpdId);
        $results = [];
        $storedPppk = 0;
        $storedReports = 0;
        $successCount = 0;
        $failedCount = 0;

        for ($skpdId = $startSkpdId; $skpdId <= $endSkpdId; $skpdId++) {
            try {
                if ($skpdId > $startSkpdId) {
                    $this->resetPortalSession();
                    $this->authenticatePortal($username, $password);
                }

                $skpdLogin = $this->loginAsSkpd($skpdId, $skpdActions[$skpdId] ?? null);
                $pppk = $this->getPppkData($skpdId, true);
                $storedForSkpd = (int) ($pppk['stored_rows'] ?? 0);
                $storedReportsForSkpd = 0;

                foreach (($pppk['data']['rows'] ?? []) as $person) {
                    $pppkId = (string) ($person['pppk_id'] ?? '');
                    if ($pppkId === '') {
                        continue;
                    }

                    $months = [];
                    for ($date = $startDate->copy()->startOfMonth(); $date->lte($endDate); $date->addMonth()) {
                        $months[$date->format('Y-m')] = [$date->format('m'), $date->format('Y')];
                    }

                    foreach ($months as [$month, $year]) {
                        $report = $this->getPppkMonthlyPresensi($skpdId, $person, $month, $year, true, $dateStart, $dateEnd);
                        $storedReportsForSkpd += (int) ($report['stored_rows'] ?? 0);
                    }
                }

                $storedPppk += $storedForSkpd;
                $storedReports += $storedReportsForSkpd;
                $successCount++;
                $results[] = [
                    'skpd_id' => $skpdId,
                    'success' => true,
                    'stored_pppk_rows' => $storedForSkpd,
                    'stored_report_rows' => $storedReportsForSkpd,
                    'skpd_login' => $skpdLogin,
                ];
            } catch (Throwable $throwable) {
                $failedCount++;
                $results[] = [
                    'skpd_id' => $skpdId,
                    'success' => false,
                    'message' => $throwable->getMessage(),
                ];

                Log::error('Absensi PPPK fetch failed', [
                    'skpd_id' => $skpdId,
                    'message' => $throwable->getMessage(),
                ]);
            }
        }

        return [
            'success' => $successCount > 0 && $failedCount === 0,
            'partial_success' => $successCount > 0 && $failedCount > 0,
            'login' => [
                'status_code' => $auth['response']->getStatusCode(),
                'redirect_history' => $this->redirectHistory($auth['response']),
                'body_preview' => $this->preview($auth['body']),
            ],
            'range' => [
                'date_start' => $dateStart,
                'date_end' => $dateEnd,
                'skpd_start' => $startSkpdId,
                'skpd_end' => $endSkpdId,
            ],
            'summary' => [
                'success_count' => $successCount,
                'failed_count' => $failedCount,
                'stored_pppk_rows' => $storedPppk,
                'stored_report_rows' => $storedReports,
                'stored_rows' => $storedReports,
            ],
            'results' => $results,
        ];
    }

    public function getCutiDataForRange(
        bool $redact = true,
        int $skpdId = self::DEFAULT_SKPD_ID,
        ?string $dateStart = null,
        ?string $dateEnd = null,
        bool $persistToDatabase = false
    ): array
    {
        $page = $this->getCutiPage($dateStart, $dateEnd);

        if (! ($page['success'] ?? false)) {
            return [
                'success' => false,
                'message' => 'Halaman cuti tidak bisa diakses. Login terlebih dahulu.',
                'page' => $page,
            ];
        }

        $body = is_string($page['body'] ?? null) ? (string) $page['body'] : '';
        $parsed = $this->parseCutiHtml($body, $redact);
        $filtered = $this->filterCutiByDateRange($parsed, $dateStart, $dateEnd);
        $parsed = $filtered['cuti'];
        $maxPage = $this->maxPaginationPage($body, self::ADMIN_CUTI_PATH);

        for ($pageNumber = 2; $pageNumber <= $maxPage; $pageNumber++) {
            if ($filtered['all_dated_rows_before_start'] ?? false) {
                break;
            }

            $nextPage = $this->getCutiPage($dateStart, $dateEnd, $pageNumber);
            if (! ($nextPage['success'] ?? false)) {
                break;
            }

            $nextBody = is_string($nextPage['body'] ?? null) ? (string) $nextPage['body'] : '';
            $nextParsed = $this->parseCutiHtml($nextBody, $redact);
            $filtered = $this->filterCutiByDateRange($nextParsed, $dateStart, $dateEnd);
            $parsed = $this->mergeCutiData($parsed, $filtered['cuti']);
        }

        $this->saveCutiData($parsed, [
            'path' => self::ADMIN_CUTI_PATH,
                'skpd_id' => $skpdId,
                'date_start' => $dateStart,
                'date_end' => $dateEnd,
                'fetched_at' => now()->toISOString(),
                'redacted' => $redact,
            ]);
        $storedRows = $persistToDatabase ? $this->storeCutiRows($parsed, [
            'skpd_id' => $skpdId,
            'date_start' => $dateStart,
            'date_end' => $dateEnd,
            'fetched_at' => now(),
        ]) : 0;

        return [
            'success' => true,
            'page' => [
                'path' => self::ADMIN_CUTI_PATH,
                'status_code' => $page['status_code'],
                'redirect_history' => $page['redirect_history'],
                'body_preview' => $page['body_preview'],
                'max_page' => $maxPage,
            ],
            'cuti' => $parsed,
            'stored_rows' => $storedRows,
        ];
    }

    public function loginAsSkpd(int $skpdId = self::DEFAULT_SKPD_ID, ?array $cachedAction = null): array
    {
        $listingPath = '/superadmin/skpd';
        $listing = $cachedAction['listing'] ?? null;
        $action = $cachedAction;

        if ($action === null) {
            $listingResponse = $this->request('GET', $listingPath);
            $listingBody = (string) $listingResponse->getBody();
            $action = $this->extractSkpdLoginAction($listingBody, $skpdId);
            $listing = [
                'path' => $listingPath,
                'status_code' => $listingResponse->getStatusCode(),
                'redirect_history' => $this->redirectHistory($listingResponse),
            ];
        }

        $method = $action['method'] ?? 'GET';
        $path = $action['url'] ?? $this->skpdLoginPath($skpdId);
        $options = [
            'headers' => [
                'Referer' => $this->baseUrl . $listingPath,
            ],
        ];

        if ($method === 'POST') {
            $options['form_params'] = $action['form_params'] ?? [];
        }

        $response = $this->request($method, $path, $options);
        $body = (string) $response->getBody();

        return [
            'success' => ! $this->isLoginPage($body) && $response->getStatusCode() < 500,
            'action' => $action,
            'listing' => $listing,
            'path' => $path,
            'status_code' => $response->getStatusCode(),
            'redirect_history' => $this->redirectHistory($response),
            'body_preview' => $this->preview($body),
        ];
    }

    public function getCutiPage(?string $dateStart = null, ?string $dateEnd = null, ?int $page = null): array
    {
        $options = [];
        $query = $this->buildDateRangeQuery($dateStart, $dateEnd);
        if ($page !== null && $page > 1) {
            $query['page'] = $page;
        }

        if ($query !== []) {
            $options['query'] = $query;
        }

        $response = $this->request('GET', self::ADMIN_CUTI_PATH, $options);
        $body = (string) $response->getBody();

        return [
            'success' => ! $this->isLoginPage($body) && ! $this->isSkpdListingPage($body) && $response->getStatusCode() === 200,
            'path' => self::ADMIN_CUTI_PATH,
            'status_code' => $response->getStatusCode(),
            'redirect_history' => $this->redirectHistory($response),
            'body' => $body,
            'body_preview' => $this->preview($body),
        ];
    }

    public function getPegawaiPage(?int $page = null): array
    {
        $options = [];
        if ($page !== null && $page > 1) {
            $options['query'] = ['page' => $page];
        }

        $response = $this->request('GET', self::SUPERADMIN_PEGAWAI_PATH, $options);
        $body = (string) $response->getBody();

        return [
            'success' => ! $this->isLoginPage($body) && $response->getStatusCode() === 200,
            'path' => self::SUPERADMIN_PEGAWAI_PATH,
            'status_code' => $response->getStatusCode(),
            'redirect_history' => $this->redirectHistory($response),
            'body' => $body,
            'body_preview' => $this->preview($body),
        ];
    }

    public function getDailyReportForDate(int $skpdId, string $date, bool $persistToDatabase = false): array
    {
        $reportPage = $this->getLaporanPage();
        if (! ($reportPage['success'] ?? false)) {
            return [
                'success' => false,
                'message' => 'Halaman laporan tidak bisa diakses.',
                'page' => $reportPage,
            ];
        }

        $form = $this->extractDailyReportPrintForm((string) ($reportPage['body'] ?? ''));
        $response = $this->request($form['method'], $form['url'], [
            'query' => [
                '_token' => $form['token'],
                'tanggal' => $date,
                'jenis' => 'pdf',
            ],
            'headers' => [
                'Referer' => $this->baseUrl . self::ADMIN_LAPORAN_PATH,
            ],
        ]);
        $body = (string) $response->getBody();

        if ($this->isLoginPage($body)) {
            return [
                'success' => false,
                'message' => 'Sesi laporan sudah tidak aktif.',
                'page' => [
                    'path' => $form['url'],
                    'status_code' => $response->getStatusCode(),
                    'redirect_history' => $this->redirectHistory($response),
                    'body_preview' => $this->preview($body),
                ],
            ];
        }

        $parsed = $this->parseDailyReportHtml($body, $skpdId, $date);
        $storedRows = $persistToDatabase ? $this->storeDailyReportRows($parsed, [
            'skpd_id' => $skpdId,
            'fetched_at' => now(),
        ]) : 0;

        return [
            'success' => true,
            'page' => [
                'path' => $form['url'],
                'status_code' => $response->getStatusCode(),
                'redirect_history' => $this->redirectHistory($response),
                'body_preview' => $this->preview($body),
            ],
            'report' => $parsed,
            'stored_rows' => $storedRows,
        ];
    }

    public function getLaporanPage(): array
    {
        $response = $this->request('GET', self::ADMIN_LAPORAN_PATH);
        $body = (string) $response->getBody();

        return [
            'success' => ! $this->isLoginPage($body) && $response->getStatusCode() === 200,
            'path' => self::ADMIN_LAPORAN_PATH,
            'status_code' => $response->getStatusCode(),
            'redirect_history' => $this->redirectHistory($response),
            'body' => $body,
            'body_preview' => $this->preview($body),
        ];
    }

    public function getPppkData(int $skpdId, bool $persistToDatabase = false): array
    {
        $response = $this->request('GET', self::ADMIN_PPPK_PATH);
        $body = (string) $response->getBody();
        $parsed = $this->parsePppkHtml($body, $skpdId);
        $storedRows = $persistToDatabase ? $this->storePppkRows($parsed, [
            'skpd_id' => $skpdId,
            'fetched_at' => now(),
        ]) : 0;

        return [
            'success' => ! $this->isLoginPage($body) && $response->getStatusCode() === 200,
            'page' => [
                'path' => self::ADMIN_PPPK_PATH,
                'status_code' => $response->getStatusCode(),
                'redirect_history' => $this->redirectHistory($response),
                'body_preview' => $this->preview($body),
            ],
            'data' => $parsed,
            'stored_rows' => $storedRows,
        ];
    }

    protected function parseCutiHtml(string $html, bool $redact): array
    {
        $crawler = $this->createCrawler($html, $this->baseUrl . self::ADMIN_CUTI_PATH);
        $title = trim($crawler->filter('title')->first()->text(''));
        $tables = [];

        $crawler->filter('table')->each(function (Crawler $table, int $index) use (&$tables, $redact) {
            $headers = $this->extractTableHeaders($table);
            $rows = $this->extractTableRows($table, $headers, $redact);

            $tables[] = [
                'index' => $index,
                'headers' => $headers,
                'row_count' => count($rows),
                'rows' => $rows,
            ];
        });

        return [
            'title' => $title,
            'table_count' => count($tables),
            'tables' => $tables,
        ];
    }

    protected function parsePegawaiHtml(string $html): array
    {
        $crawler = $this->createCrawler($html, $this->baseUrl . self::SUPERADMIN_PEGAWAI_PATH);
        $rows = [];

        $crawler->filter('table tbody tr')->each(function (Crawler $row) use (&$rows) {
            if ($row->filter('td')->count() === 0) {
                return;
            }

            $cells = [];
            $row->filter('td')->each(function (Crawler $cell) use (&$cells) {
                $html = (string) $cell->html();
                $text = strip_tags(preg_replace('/<br\s*\/?>/i', "\n", $html) ?? $html);
                $parts = [];

                foreach (preg_split('/\R/u', html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?: [] as $part) {
                    $normalized = $this->normalizeText($part);
                    if ($normalized !== '') {
                        $parts[] = $normalized;
                    }
                }

                $cells[] = $parts;
            });

            $identity = $cells[1] ?? [];
            $unit = $cells[2] ?? [];
            $links = [];

            $row->filter('a[href]')->each(function (Crawler $link) use (&$links) {
                $href = trim((string) $link->attr('href'));
                if ($href === '') {
                    return;
                }

                $links[] = [
                    'label' => $this->normalizeText($link->text('')) ?: $href,
                    'url' => $this->resolveUrl($href, $this->baseUrl . self::SUPERADMIN_PEGAWAI_PATH) ?? $href,
                ];
            });

            $historyUrl = null;
            foreach ($links as $link) {
                if (str_contains(strtolower((string) ($link['url'] ?? '')), '/history')) {
                    $historyUrl = $link['url'];
                    break;
                }
            }

            $rows[] = [
                'nomor' => $this->normalizeText($cells[0][0] ?? ''),
                'nip' => $this->normalizeText($identity[0] ?? ''),
                'nama' => $this->normalizeText($identity[1] ?? ''),
                'pangkat_golongan' => $this->normalizeText($identity[2] ?? ''),
                'skpd' => $this->normalizeText($unit[0] ?? ''),
                'jabatan' => $this->normalizeText($unit[1] ?? ''),
                'puskesmas' => $this->normalizeText(($cells[3][0] ?? '')),
                'device_id' => $this->normalizeText(($cells[4][0] ?? '')),
                'history_url' => $historyUrl,
                '_links' => $links,
            ];
        });

        return [
            'row_count' => count($rows),
            'rows' => $rows,
        ];
    }

    protected function parsePppkHtml(string $html, int $skpdId): array
    {
        $crawler = $this->createCrawler($html, $this->baseUrl . self::ADMIN_PPPK_PATH);
        $skpd = $this->skpdInfo($skpdId);
        $rows = [];

        $crawler->filter('table tbody tr')->each(function (Crawler $row) use (&$rows, $skpdId, $skpd) {
            if ($row->filter('td')->count() === 0) {
                return;
            }

            $cells = [];
            $row->filter('td')->each(function (Crawler $cell) use (&$cells) {
                $cells[] = $this->cellLines($cell);
            });

            $identity = $cells[1] ?? [];
            [$nama, $nip] = $this->splitNameAndNip($identity);
            $jabatan = $this->normalizeText(implode(' ', array_slice($identity, 2)));
            if ($jabatan === '' && count($identity) === 1) {
                $text = $this->normalizeText($identity[0]);
                if (preg_match('/^(.+?)(\d{18})(.+)$/u', $text, $matches)) {
                    $nama = $this->normalizeText($matches[1]);
                    $nip = $this->normalizeText($matches[2]);
                    $jabatan = $this->normalizeText($matches[3]);
                }
            }

            $presensiUrl = null;
            $pppkId = null;
            $row->filter('a[href]')->each(function (Crawler $link) use (&$presensiUrl, &$pppkId) {
                $href = trim((string) $link->attr('href'));
                if ($href === '' || ! str_contains($href, '/presensi')) {
                    return;
                }

                $presensiUrl = $href;
                if (preg_match('/\/admin\/pppk\/([^\/]+)\/presensi/i', $href, $matches)) {
                    $pppkId = $matches[1];
                }
            });

            $rows[] = [
                'nomor' => $this->normalizeText($cells[0][0] ?? ''),
                'pppk_id' => $pppkId,
                'skpd_id' => $skpdId,
                'kode_skpd' => $skpd['kode'],
                'nama_skpd' => $skpd['nama'],
                'nip' => $nip,
                'nama' => $nama,
                'jabatan' => $jabatan,
                'pangkat' => $this->normalizeText(implode(' ', $cells[2] ?? [])),
                'tanggal_lahir' => $this->normalizeDateValue($cells[3][0] ?? null),
                'jenis_presensi' => $this->normalizeText($cells[4][0] ?? ''),
                'status_asn' => $this->normalizeText($cells[5][0] ?? ''),
                'presensi_url' => $presensiUrl,
            ];
        });

        return [
            'skpd_id' => $skpdId,
            'kode_skpd' => $skpd['kode'],
            'nama_skpd' => $skpd['nama'],
            'row_count' => count($rows),
            'rows' => $rows,
        ];
    }

    public function getPppkMonthlyPresensi(
        int $skpdId,
        array $person,
        string $month,
        string $year,
        bool $persistToDatabase = false,
        ?string $dateStart = null,
        ?string $dateEnd = null
    ): array {
        $pppkId = (string) ($person['pppk_id'] ?? '');
        if ($pppkId === '') {
            return ['success' => false, 'message' => 'ID PPPK kosong.', 'stored_rows' => 0];
        }

        $path = self::ADMIN_PPPK_PATH . '/' . $pppkId . '/presensi/' . str_pad($month, 2, '0', STR_PAD_LEFT) . '/' . $year;
        $response = $this->request('GET', $path);
        $body = (string) $response->getBody();
        $parsed = $this->parsePppkPresensiHtml($body, $skpdId, $person);
        $storedRows = $persistToDatabase ? $this->storePppkReportRows($parsed, [
            'skpd_id' => $skpdId,
            'fetched_at' => now(),
            'date_start' => $dateStart,
            'date_end' => $dateEnd,
        ]) : 0;

        return [
            'success' => ! $this->isLoginPage($body) && $response->getStatusCode() === 200,
            'page' => [
                'path' => $path,
                'status_code' => $response->getStatusCode(),
                'redirect_history' => $this->redirectHistory($response),
                'body_preview' => $this->preview($body),
            ],
            'report' => $parsed,
            'stored_rows' => $storedRows,
        ];
    }

    protected function parsePppkPresensiHtml(string $html, int $skpdId, array $person): array
    {
        $crawler = $this->createCrawler($html, $this->baseUrl . self::ADMIN_PPPK_PATH);
        $skpd = $this->skpdInfo($skpdId);
        $rows = [];

        $reportTable = $crawler->filter('table')->reduce(function (Crawler $table) {
            $text = strtolower($this->normalizeText($table->text('')));

            return str_contains($text, 'tanggal')
                && str_contains($text, 'jam masuk')
                && str_contains($text, 'jam pulang');
        })->first();

        if ($reportTable->count() > 0) {
            $reportTable->filter('tbody tr')->each(function (Crawler $row) use (&$rows) {
                $cells = [];
                $row->filter('td')->each(function (Crawler $cell) use (&$cells) {
                    $cells[] = $this->normalizeText($cell->text(''));
                });

                $nomor = $this->normalizeText($cells[0] ?? '');
                $tanggal = $this->normalizeDateValue($cells[1] ?? null);
                if ($nomor === '' || ! ctype_digit($nomor) || $tanggal === null) {
                    return;
                }

                $rows[] = [
                    'nomor' => $nomor,
                    'tanggal' => $tanggal,
                    'hari' => $this->normalizeText($cells[2] ?? ''),
                    'jam_masuk' => $this->normalizeText($cells[3] ?? ''),
                    'jam_pulang' => $this->normalizeText($cells[4] ?? ''),
                    'keterangan' => $this->normalizeText($cells[5] ?? ''),
                    'telat' => is_numeric($cells[6] ?? null) ? (int) $cells[6] : null,
                    'lebih_awal' => is_numeric($cells[7] ?? null) ? (int) $cells[7] : null,
                ];
            });
        }

        return [
            'pppk_id' => $person['pppk_id'] ?? null,
            'skpd_id' => $skpdId,
            'kode_skpd' => $skpd['kode'],
            'nama_skpd' => $skpd['nama'],
            'nip' => $person['nip'] ?? null,
            'nama_pegawai' => $person['nama'] ?? null,
            'jabatan' => $person['jabatan'] ?? null,
            'row_count' => count($rows),
            'rows' => $rows,
        ];
    }

    protected function parseDailyReportHtml(string $html, int $skpdId, string $date): array
    {
        $crawler = $this->createCrawler($html, $this->baseUrl . self::ADMIN_LAPORAN_TANGGAL_PATH);
        $title = $this->normalizeText($crawler->filter('p strong')->first()->text(''));
        $hari = null;
        $tanggalLabel = null;
        $rows = [];

        $crawler->filter('table')->first()->filter('tr')->each(function (Crawler $row) use (&$hari, &$tanggalLabel) {
            $cells = [];
            $row->filter('td,th')->each(function (Crawler $cell) use (&$cells) {
                $cells[] = $this->normalizeText($cell->text(''));
            });

            $label = strtolower((string) ($cells[0] ?? ''));
            $value = ltrim((string) ($cells[1] ?? ''), ': ');

            if ($label === 'hari') {
                $hari = $this->normalizeText($value);
            }

            if ($label === 'tanggal') {
                $tanggalLabel = $this->normalizeText($value);
            }
        });

        $reportTable = $crawler->filter('table')->reduce(function (Crawler $table) {
            $text = strtolower($this->normalizeText($table->text('')));

            return str_contains($text, 'nama / nip')
                && str_contains($text, 'pagi')
                && str_contains($text, 'pulang')
                && str_contains($text, 'apel');
        })->first();

        if ($reportTable->count() > 0) {
            $reportTable->filter('tr')->each(function (Crawler $row) use (&$rows) {
                $cells = [];
                $row->filter('td,th')->each(function (Crawler $cell) use (&$cells) {
                    $cells[] = $this->cellLines($cell);
                });

                $first = $this->normalizeText($cells[0][0] ?? '');
                if ($first === '' || strtolower($first) === 'no' || ! ctype_digit($first)) {
                    return;
                }

                [$nama, $nip] = $this->splitNameAndNip($cells[1] ?? []);

                $rows[] = [
                    'nomor' => $first,
                    'nama_pegawai' => $nama,
                    'nip' => $nip,
                    'pangkat' => $this->normalizeText(implode(' ', $cells[2] ?? [])),
                    'jabatan' => $this->normalizeText(implode(' ', $cells[3] ?? [])),
                    'pagi' => $this->normalizeText($cells[4][0] ?? ''),
                    'pulang' => $this->normalizeText($cells[5][0] ?? ''),
                    'apel' => $this->normalizeText($cells[6][0] ?? ''),
                ];
            });
        }

        $skpd = $this->skpdInfo($skpdId);

        return [
            'title' => $title,
            'skpd_id' => $skpdId,
            'kode_skpd' => $skpd['kode'],
            'nama_skpd' => $skpd['nama'],
            'hari' => $hari,
            'tanggal_label' => $tanggalLabel,
            'tanggal' => $this->normalizeDateValue($tanggalLabel) ?? $this->normalizeDateValue($date) ?? $date,
            'row_count' => count($rows),
            'rows' => $rows,
        ];
    }

    protected function storeDailyReportRows(array $report, array $meta): int
    {
        $stored = 0;
        $rows = is_array($report['rows'] ?? null) ? $report['rows'] : [];
        $tanggal = $this->normalizeDateValue((string) ($report['tanggal'] ?? ''));
        $skpdId = (int) ($report['skpd_id'] ?? $meta['skpd_id'] ?? self::DEFAULT_SKPD_ID);

        foreach ($rows as $row) {
            if (! is_array($row) || $tanggal === null) {
                continue;
            }

            $rowHash = hash('sha256', json_encode([
                'skpd_id' => $skpdId,
                'tanggal' => $tanggal,
                'nip' => $row['nip'] ?? null,
                'nomor' => $row['nomor'] ?? null,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            AbsensiDailyReport::query()->updateOrCreate(
                ['row_hash' => $rowHash],
                [
                    'skpd_id' => $skpdId,
                    'kode_skpd' => $report['kode_skpd'] ?? null,
                    'nama_skpd' => $report['nama_skpd'] ?? null,
                    'tanggal' => $tanggal,
                    'hari' => $report['hari'] ?? null,
                    'nama_pegawai' => $row['nama_pegawai'] ?: null,
                    'nip' => $row['nip'] ?: null,
                    'pangkat' => $row['pangkat'] ?: null,
                    'jabatan' => $row['jabatan'] ?: null,
                    'pagi' => $row['pagi'] ?: null,
                    'pulang' => $row['pulang'] ?: null,
                    'apel' => $row['apel'] ?: null,
                    'row_data' => $row,
                    'fetched_at' => $meta['fetched_at'] ?? now(),
                ]
            );
            $stored++;
        }

        return $stored;
    }

    protected function storePppkRows(array $report, array $meta): int
    {
        $stored = 0;
        $rows = is_array($report['rows'] ?? null) ? $report['rows'] : [];
        $skpdId = (int) ($report['skpd_id'] ?? $meta['skpd_id'] ?? self::DEFAULT_SKPD_ID);

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $rowHash = hash('sha256', json_encode([
                'skpd_id' => $skpdId,
                'pppk_id' => $row['pppk_id'] ?? null,
                'nip' => $row['nip'] ?? null,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            $nip = ($row['nip'] ?? null) ?: null;
            $unitKerja = $this->setdaPppkUnitKerjaByNip($nip);

            AbsensiPppk::query()->updateOrCreate(
                $nip !== null ? ['nip' => $nip] : ['row_hash' => $rowHash],
                [
                    'pppk_id' => $row['pppk_id'] ?: null,
                    'skpd_id' => $skpdId,
                    'kode_skpd' => $row['kode_skpd'] ?? null,
                    'nama_skpd' => $row['nama_skpd'] ?? null,
                    'unit_kerja' => $unitKerja,
                    'nip' => $nip,
                    'nama' => $row['nama'] ?: null,
                    'jabatan' => $row['jabatan'] ?: null,
                    'pangkat' => $row['pangkat'] ?: null,
                    'tanggal_lahir' => $row['tanggal_lahir'] ?: null,
                    'jenis_presensi' => $row['jenis_presensi'] ?: null,
                    'status_asn' => $row['status_asn'] ?: null,
                    'presensi_url' => $row['presensi_url'] ?: null,
                    'row_data' => $row,
                    'fetched_at' => $meta['fetched_at'] ?? now(),
                ]
            );
            $stored++;
        }

        return $stored;
    }

    protected function storePppkReportRows(array $report, array $meta): int
    {
        $stored = 0;
        $rows = is_array($report['rows'] ?? null) ? $report['rows'] : [];
        $skpdId = (int) ($report['skpd_id'] ?? $meta['skpd_id'] ?? self::DEFAULT_SKPD_ID);
        $dateStart = $this->normalizeDateValue($meta['date_start'] ?? null);
        $dateEnd = $this->normalizeDateValue($meta['date_end'] ?? null);

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $tanggal = $this->normalizeDateValue((string) ($row['tanggal'] ?? ''));
            if ($tanggal === null) {
                continue;
            }

            if ($dateStart !== null && $tanggal < $dateStart) {
                continue;
            }

            if ($dateEnd !== null && $tanggal > $dateEnd) {
                continue;
            }

            $rowHash = hash('sha256', json_encode([
                'skpd_id' => $skpdId,
                'pppk_id' => $report['pppk_id'] ?? null,
                'tanggal' => $tanggal,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            $nip = ($report['nip'] ?? null) ?: null;
            $unitKerja = $this->setdaPppkUnitKerjaByNip($nip);

            AbsensiPppkReport::query()->updateOrCreate(
                ['row_hash' => $rowHash],
                [
                    'pppk_id' => $report['pppk_id'] ?: null,
                    'skpd_id' => $skpdId,
                    'kode_skpd' => $report['kode_skpd'] ?? null,
                    'nama_skpd' => $report['nama_skpd'] ?? null,
                    'unit_kerja' => $unitKerja,
                    'nip' => $nip,
                    'nama_pegawai' => ($report['nama_pegawai'] ?? null) ?: null,
                    'jabatan' => ($report['jabatan'] ?? null) ?: null,
                    'tanggal' => $tanggal,
                    'hari' => $row['hari'] ?: null,
                    'jam_masuk' => $row['jam_masuk'] ?: null,
                    'jam_pulang' => $row['jam_pulang'] ?: null,
                    'keterangan' => $row['keterangan'] ?: null,
                    'telat' => $row['telat'] ?? null,
                    'lebih_awal' => $row['lebih_awal'] ?? null,
                    'row_data' => $row,
                    'fetched_at' => $meta['fetched_at'] ?? now(),
                ]
            );
            $stored++;
        }

        return $stored;
    }

    private function setdaPppkUnitKerjaByNip(?string $nip): ?string
    {
        if ($nip === null || $nip === '') {
            return null;
        }

        return [
            '199305112024212030' => 'Sekretariat Daerah - Bagian Pemerintahan',
            '199205312024211006' => 'Sekretariat Daerah - Bagian Pengadaan Barang dan Jasa',
            '199802062024211003' => 'Sekretariat Daerah - Bagian Pengadaan Barang dan Jasa',
            '198708192025212001' => 'Sekretariat Daerah - Bagian Pengadaan Barang dan Jasa',
            '199808292025211003' => 'Sekretariat Daerah - Bagian Umum',
            '199305252025211010' => 'Sekretariat Daerah - Bagian Pemerintahan',
            '197801012025212009' => 'Sekretariat Daerah - Bagian Kesejahteraan Rakyat',
            '199901212025211003' => 'Sekretariat Daerah - Bagian Umum',
            '200008312025211001' => 'Sekretariat Daerah - Bagian Protokol dan Komunikasi Pimpinan',
            '199610302025211002' => 'Sekretariat Daerah - Bagian Organisasi',
            '199911112025211003' => 'Sekretariat Daerah - Bagian Perekonomian dan Sumber Daya Alam',
            '199410232025212002' => 'Sekretariat Daerah - Bagian Protokol dan Komunikasi Pimpinan',
            '198304112025211007' => 'Sekretariat Daerah - Bagian Protokol dan Komunikasi Pimpinan',
            '199502282025211004' => 'Sekretariat Daerah - Bagian Umum',
            '199610282025211004' => 'Sekretariat Daerah - Bagian Umum',
            '198909292025212008' => 'Sekretariat Daerah - Bagian Umum',
        ][$nip] ?? null;
    }

    protected function extractDailyReportPrintForm(string $html): array
    {
        $crawler = $this->createCrawler($html, $this->baseUrl . self::ADMIN_LAPORAN_PATH);
        $form = $crawler->filter('form')->reduce(function (Crawler $form) {
            $action = strtolower((string) $form->attr('action'));
            $text = strtolower($this->normalizeText($form->text('')));

            return str_contains($action, '/admin/laporan/tanggal')
                || (str_contains($text, 'print') && $form->filter('input[name="tanggal"]')->count() > 0);
        })->first();

        if ($form->count() === 0) {
            throw new \RuntimeException('Form print laporan harian tidak ditemukan.');
        }

        $method = strtoupper(trim((string) $form->attr('method')) ?: 'GET');
        $action = trim((string) $form->attr('action')) ?: self::ADMIN_LAPORAN_TANGGAL_PATH;
        $token = trim((string) $form->filter('input[name="_token"]')->first()->attr('value'));

        if ($token === '') {
            throw new \RuntimeException('Token print laporan harian tidak ditemukan.');
        }

        return [
            'method' => $method === 'POST' ? 'POST' : 'GET',
            'url' => $action,
            'token' => $token,
        ];
    }

    protected function splitNameAndNip(array $parts): array
    {
        $parts = array_values(array_filter(array_map(fn ($part) => $this->normalizeText((string) $part), $parts)));

        if (count($parts) >= 2) {
            if (preg_match('/^\d{8,}$/', $parts[0]) === 1) {
                return [$parts[1] ?? null, $parts[0]];
            }

            if (preg_match('/^\d{8,}$/', $parts[1]) === 1) {
                return [$parts[0] ?? null, $parts[1]];
            }
        }

        $text = $this->normalizeText(implode(' ', $parts));
        if (preg_match('/^(.*?)[\s,]*(\d{8,})$/u', $text, $matches) === 1) {
            return [$this->normalizeText($matches[1]), $matches[2]];
        }

        return [$text !== '' ? $text : null, null];
    }

    protected function cellLines(Crawler $cell): array
    {
        $html = (string) $cell->html();
        $text = strip_tags(preg_replace('/<br\s*\/?>/i', "\n", $html) ?? $html);
        $lines = [];

        foreach (preg_split('/\R/u', html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?: [] as $part) {
            $normalized = $this->normalizeText($part);
            if ($normalized !== '') {
                $lines[] = $normalized;
            }
        }

        return $lines;
    }

    protected function mergePegawaiData(array $base, array $next): array
    {
        $baseRows = is_array($base['rows'] ?? null) ? $base['rows'] : [];
        $nextRows = is_array($next['rows'] ?? null) ? $next['rows'] : [];
        $base['rows'] = array_merge($baseRows, $nextRows);
        $base['row_count'] = count($base['rows']);

        return $base;
    }

    protected function storePegawaiRows(array $pegawai, array $meta): int
    {
        $stored = 0;
        $rows = is_array($pegawai['rows'] ?? null) ? $pegawai['rows'] : [];

        foreach ($rows as $row) {
            if (! is_array($row) || $row === []) {
                continue;
            }

            $historyUrl = (string) ($row['history_url'] ?? '');
            preg_match('/\/pegawai\/([^\/]+)\/history/i', $historyUrl, $matches);
            $pegawaiId = $matches[1] ?? null;
            $rowHash = $pegawaiId !== null
                ? hash('sha256', 'pegawai:' . $pegawaiId)
                : hash('sha256', json_encode([
                    'nip' => $row['nip'] ?? null,
                    'nama' => $row['nama'] ?? null,
                    'skpd' => $row['skpd'] ?? null,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            AbsensiPegawai::query()->updateOrCreate(
                ['row_hash' => $rowHash],
                [
                    'pegawai_id' => $pegawaiId,
                    'nip' => $row['nip'] ?: null,
                    'nama' => $row['nama'] ?: null,
                    'pangkat_golongan' => $row['pangkat_golongan'] ?: null,
                    'skpd' => $row['skpd'] ?: null,
                    'jabatan' => $row['jabatan'] ?: null,
                    'puskesmas' => $row['puskesmas'] ?: null,
                    'device_id' => $row['device_id'] ?: null,
                    'history_url' => $historyUrl !== '' ? $historyUrl : null,
                    'row_data' => $row,
                    'fetched_at' => $meta['fetched_at'] ?? now(),
                ]
            );
            $stored++;
        }

        return $stored;
    }

    protected function mergeCutiData(array $base, array $next): array
    {
        $baseTables = is_array($base['tables'] ?? null) ? $base['tables'] : [];
        $nextTables = is_array($next['tables'] ?? null) ? $next['tables'] : [];

        foreach ($nextTables as $index => $nextTable) {
            $nextRows = is_array($nextTable['rows'] ?? null) ? $nextTable['rows'] : [];
            if (! isset($baseTables[$index])) {
                $nextTable['row_count'] = count($nextRows);
                $baseTables[$index] = $nextTable;
                continue;
            }

            $baseRows = is_array($baseTables[$index]['rows'] ?? null) ? $baseTables[$index]['rows'] : [];
            $baseTables[$index]['rows'] = array_merge($baseRows, $nextRows);
            $baseTables[$index]['row_count'] = count($baseTables[$index]['rows']);
        }

        $base['tables'] = $baseTables;
        $base['table_count'] = count($baseTables);

        return $base;
    }

    protected function filterCutiByDateRange(array $cuti, ?string $dateStart, ?string $dateEnd): array
    {
        $start = $this->normalizeDateValue($dateStart);
        $end = $this->normalizeDateValue($dateEnd);

        if ($start === null && $end === null) {
            return [
                'cuti' => $cuti,
                'all_dated_rows_before_start' => false,
            ];
        }

        $tables = is_array($cuti['tables'] ?? null) ? $cuti['tables'] : [];
        $allDatedRowsBeforeStart = true;
        $hasDatedRows = false;

        foreach ($tables as $tableIndex => $table) {
            $rows = is_array($table['rows'] ?? null) ? $table['rows'] : [];
            $filteredRows = [];

            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $rowStart = $this->normalizeDateValue($this->firstMatchingValue($row, ['tgl mulai', 'tanggal mulai', 'mulai', 'dari']));
                $rowEnd = $this->normalizeDateValue($this->firstMatchingValue($row, ['tgl selesai', 'tanggal selesai', 'selesai', 'sampai']));

                if ($rowStart === null && $rowEnd === null) {
                    $allDatedRowsBeforeStart = false;
                    continue;
                }

                $hasDatedRows = true;
                $effectiveStart = $rowStart ?? $rowEnd;
                $effectiveEnd = $rowEnd ?? $rowStart;

                if ($start === null || $effectiveEnd >= $start) {
                    $allDatedRowsBeforeStart = false;
                }

                if (($start === null || $effectiveEnd >= $start) && ($end === null || $effectiveStart <= $end)) {
                    $filteredRows[] = $row;
                }
            }

            $tables[$tableIndex]['rows'] = $filteredRows;
            $tables[$tableIndex]['row_count'] = count($filteredRows);
        }

        $cuti['tables'] = $tables;

        return [
            'cuti' => $cuti,
            'all_dated_rows_before_start' => $hasDatedRows && $allDatedRowsBeforeStart,
        ];
    }

    protected function maxPaginationPage(string $html, string $path): int
    {
        $crawler = $this->createCrawler($html, $this->baseUrl . $path);
        $maxPage = 1;

        $crawler->filter('a[href]')->each(function (Crawler $link) use (&$maxPage) {
            $href = trim((string) $link->attr('href'));
            $query = parse_url($href, PHP_URL_QUERY);
            if (! is_string($query) || $query === '') {
                return;
            }

            parse_str($query, $params);
            $page = (int) ($params['page'] ?? 0);
            if ($page > $maxPage) {
                $maxPage = $page;
            }
        });

        return $maxPage;
    }

    protected function extractTableHeaders(Crawler $table): array
    {
        $headers = [];
        $table->filter('thead tr')->first()->filter('th,td')->each(function (Crawler $cell) use (&$headers) {
            $headers[] = $this->normalizeText($cell->text(''));
        });

        if ($headers !== []) {
            return $headers;
        }

        $table->filter('tr')->first()->filter('th')->each(function (Crawler $cell) use (&$headers) {
            $headers[] = $this->normalizeText($cell->text(''));
        });

        return $headers;
    }

    protected function extractTableRows(Crawler $table, array $headers, bool $redact): array
    {
        $rows = [];
        $selector = $table->filter('tbody tr')->count() > 0 ? 'tbody tr' : 'tr';

        $table->filter($selector)->each(function (Crawler $row) use (&$rows, $headers, $redact) {
            if ($row->filter('td')->count() === 0) {
                return;
            }

            $values = [];
            $links = [];
            $row->filter('td')->each(function (Crawler $cell, int $cellIndex) use (&$values, &$links, $headers) {
                $values[] = $this->normalizeText($cell->text(''));

                $cell->filter('a[href]')->each(function (Crawler $link) use (&$links, $cellIndex, $headers) {
                    $href = trim((string) $link->attr('href'));
                    if ($href === '') {
                        return;
                    }

                    $links[] = [
                        'header' => $headers[$cellIndex] ?? 'column_' . ($cellIndex + 1),
                        'label' => $this->normalizeText($link->text('')) ?: $href,
                        'url' => $this->resolveUrl($href, $this->baseUrl . self::ADMIN_CUTI_PATH) ?? $href,
                    ];
                });
            });

            if ($values === []) {
                return;
            }

            $record = [];
            foreach ($values as $index => $value) {
                $key = $headers[$index] ?? 'column_' . ($index + 1);
                $record[$key] = $redact && $this->isSensitiveHeader($key) ? $this->redactValue($value) : $value;
            }

            if ($links !== []) {
                $record['_links'] = $links;
                $upload = $this->firstUploadLink($links);
                if ($upload !== null) {
                    $record['_upload'] = $upload;
                }
            }

            $rows[] = $record;
        });

        return $rows;
    }

    protected function isSensitiveHeader(string $header): bool
    {
        $normalized = strtolower($header);

        foreach (self::SENSITIVE_HEADER_KEYWORDS as $keyword) {
            if (str_contains($normalized, $keyword)) {
                return true;
            }
        }

        return false;
    }

    protected function redactValue(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            $length = mb_strlen($value);

            return $length <= 4 ? str_repeat('*', $length) : mb_substr($value, 0, 2) . str_repeat('*', max(0, $length - 4)) . mb_substr($value, -2);
        }

        $length = strlen($value);

        return $length <= 4 ? str_repeat('*', $length) : substr($value, 0, 2) . str_repeat('*', max(0, $length - 4)) . substr($value, -2);
    }

    protected function saveCutiData(array $cuti, array $meta = []): void
    {
        $targetPath = storage_path('scraping/absensi_cuti.json');
        $directory = dirname($targetPath);

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        file_put_contents(
            $targetPath,
            json_encode([
                'meta' => $meta,
                'cuti' => $cuti,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );

        Log::info('Absensi cuti data saved', [
            'target_path' => $targetPath,
            'table_count' => $cuti['table_count'] ?? 0,
            'redacted' => $meta['redacted'] ?? null,
        ]);
    }

    protected function storeCutiRows(array $cuti, array $meta): int
    {
        $stored = 0;
        $tables = is_array($cuti['tables'] ?? null) ? $cuti['tables'] : [];

        foreach ($tables as $table) {
            $rows = is_array($table['rows'] ?? null) ? $table['rows'] : [];

            foreach ($rows as $row) {
                if (! is_array($row) || $row === []) {
                    continue;
                }

                $skpdId = (int) ($meta['skpd_id'] ?? self::DEFAULT_SKPD_ID);
                $skpd = $this->skpdInfo($skpdId);
                $upload = is_array($row['_upload'] ?? null) ? $row['_upload'] : null;
                $payload = [
                    'skpd_id' => $skpdId,
                    'kode_skpd' => $this->firstMatchingValue($row, ['kode skpd', 'kode']) ?? $skpd['kode'],
                    'nama_skpd' => $this->firstMatchingValue($row, ['nama skpd', 'skpd', 'unit kerja', 'opd']) ?? $skpd['nama'],
                    'tanggal_mulai' => $this->normalizeDateValue($this->firstMatchingValue($row, ['tanggal mulai', 'tgl mulai', 'mulai', 'tanggal awal', 'dari tanggal', 'dari'])),
                    'tanggal_selesai' => $this->normalizeDateValue($this->firstMatchingValue($row, ['tanggal selesai', 'tgl selesai', 'selesai', 'tanggal akhir', 'sampai tanggal', 'sampai'])),
                    'jenis_cuti' => $this->firstMatchingValue($row, ['jenis cuti', 'jenis']),
                    'status' => $this->firstMatchingValue($row, ['status', 'verifikasi', 'approval']),
                    'nama_pegawai' => $this->firstMatchingValue($row, ['nama pegawai', 'nama']),
                    'nip' => $this->firstMatchingValue($row, ['nip']),
                    'upload_url' => is_array($upload) ? ($upload['url'] ?? null) : null,
                    'upload_label' => is_array($upload) ? ($upload['label'] ?? null) : null,
                    'row_data' => $row,
                    'row_hash' => hash('sha256', json_encode([
                        'skpd_id' => $skpdId,
                        'row' => $row,
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
                    'fetched_at' => $meta['fetched_at'] ?? now(),
                ];

                AbsensiCutiReport::query()->updateOrCreate(
                    ['row_hash' => $payload['row_hash']],
                    $payload
                );
                $stored++;
            }
        }

        return $stored;
    }

    protected function firstUploadLink(array $links): ?array
    {
        foreach ($links as $link) {
            $label = strtolower((string) ($link['label'] ?? ''));
            $header = strtolower((string) ($link['header'] ?? ''));
            $url = strtolower((string) ($link['url'] ?? ''));

            if (
                str_contains($label, 'upload')
                || str_contains($label, 'lihat')
                || str_contains($label, 'file')
                || str_contains($label, 'berkas')
                || str_contains($label, 'lampiran')
                || str_contains($label, 'download')
                || str_contains($header, 'upload')
                || str_contains($header, 'file')
                || str_contains($header, 'berkas')
                || str_contains($header, 'lampiran')
                || preg_match('/\.(pdf|jpg|jpeg|png|webp|doc|docx|xls|xlsx)(\?|$)/', $url) === 1
            ) {
                return $link;
            }
        }

        return $links[0] ?? null;
    }

    protected function firstMatchingValue(array $row, array $needles): ?string
    {
        foreach ($row as $key => $value) {
            if (str_starts_with((string) $key, '_') || is_array($value)) {
                continue;
            }

            $normalizedKey = strtolower($this->normalizeText((string) $key));
            foreach ($needles as $needle) {
                if ($normalizedKey === $needle || str_contains($normalizedKey, $needle)) {
                    $normalizedValue = $this->normalizeText((string) $value);

                    return $normalizedValue !== '' ? $normalizedValue : null;
                }
            }
        }

        return null;
    }

    protected function skpdInfo(int $skpdId): array
    {
        $skpd = $this->configValue('services.absensi.skpd.' . $skpdId, []);

        return [
            'kode' => is_array($skpd) ? ($skpd['kode'] ?? null) : null,
            'nama' => is_array($skpd) ? ($skpd['nama'] ?? null) : null,
        ];
    }

    protected function normalizeDateValue(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $indonesianDate = $this->normalizeIndonesianDateValue($value);
        if ($indonesianDate !== null) {
            return $indonesianDate;
        }

        foreach (['Y-m-d', 'd-m-Y', 'd/m/Y', 'Y/m/d', 'd M Y', 'd F Y'] as $format) {
            try {
                return Carbon::createFromFormat($format, $value)->toDateString();
            } catch (Throwable) {
                //
            }
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    protected function normalizeIndonesianDateValue(string $value): ?string
    {
        $months = [
            'januari' => '01',
            'februari' => '02',
            'maret' => '03',
            'april' => '04',
            'mei' => '05',
            'juni' => '06',
            'juli' => '07',
            'agustus' => '08',
            'september' => '09',
            'oktober' => '10',
            'november' => '11',
            'desember' => '12',
        ];

        if (preg_match('/\b(\d{1,2})\s+([A-Za-z]+)\s+(\d{4})\b/u', strtolower($value), $matches) !== 1) {
            return null;
        }

        $month = $months[$matches[2]] ?? null;
        if ($month === null) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $matches[3] . '-' . $month . '-' . str_pad($matches[1], 2, '0', STR_PAD_LEFT))->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    protected function buildDateRangeQuery(?string $dateStart, ?string $dateEnd): array
    {
        $dateStart = $this->normalizeDateValue($dateStart);
        $dateEnd = $this->normalizeDateValue($dateEnd);

        if ($dateStart === null && $dateEnd === null) {
            return [];
        }

        return array_filter([
            'tanggal_awal' => $dateStart,
            'tanggal_akhir' => $dateEnd,
            'tgl_awal' => $dateStart,
            'tgl_akhir' => $dateEnd,
            'start_date' => $dateStart,
            'end_date' => $dateEnd,
            'date_start' => $dateStart,
            'date_end' => $dateEnd,
            'from' => $dateStart,
            'to' => $dateEnd,
        ], static fn ($value) => $value !== null);
    }

    protected function authenticatePortal(string $username, string $password): array
    {
        $loginPage = $this->request('GET', '/');
        $html = (string) $loginPage->getBody();
        $csrfToken = $this->extractCsrfTokenFromLoginPage($html);

        if ($csrfToken === null) {
            throw new \RuntimeException('Tidak menemukan CSRF token pada halaman login Absensi.');
        }

        $response = $this->request('POST', '/login', [
            'form_params' => [
                '_token' => $csrfToken,
                'username' => $username,
                'password' => $password,
            ],
        ]);

        $body = (string) $response->getBody();
        $this->syncCookiesToLaravelSession();

        return [
            'response' => $response,
            'body' => $body,
        ];
    }

    protected function resetPortalSession(): void
    {
        $this->cookieJar = CookieJar::fromArray([], $this->cookieHost());
        $this->syncCookiesToLaravelSession();
    }

    protected function request(string $method, string $uri, array $options = []): ResponseInterface
    {
        $requestOptions = array_replace_recursive([
            'cookies' => $this->cookieJar,
            'http_errors' => false,
            'allow_redirects' => [
                'max' => 10,
                'track_redirects' => true,
                'referer' => true,
            ],
            'headers' => $this->defaultHeaders(),
        ], $options);

        $attempts = 3;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $response = $this->client->request($method, $uri, $requestOptions);
                $this->syncCookiesToLaravelSession();
                $this->logHttpExchange($method, $uri, $response);

                return $response;
            } catch (Throwable $throwable) {
                if ($attempt < $attempts && $this->isRetryableRequestFailure($throwable)) {
                    Log::warning('Absensi request retrying after transient failure', [
                        'method' => $method,
                        'uri' => $uri,
                        'attempt' => $attempt,
                        'message' => $throwable->getMessage(),
                    ]);

                    usleep(300000 * $attempt);
                    continue;
                }

                Log::error('Absensi request failed', [
                    'method' => $method,
                    'uri' => $uri,
                    'message' => $throwable->getMessage(),
                ]);

                throw $throwable;
            }
        }

        throw new \RuntimeException('Request Absensi gagal tanpa response.');
    }

    protected function isRetryableRequestFailure(Throwable $throwable): bool
    {
        $message = strtolower($throwable->getMessage());

        return str_contains($message, 'connection refused')
            || str_contains($message, 'connection reset')
            || str_contains($message, 'timed out')
            || str_contains($message, 'timeout')
            || str_contains($message, 'temporarily unavailable')
            || str_contains($message, 'curl error 7')
            || str_contains($message, 'curl error 28');
    }

    protected function skpdLoginPath(int $skpdId): string
    {
        return '/superadmin/skpd/' . max(1, $skpdId);
    }

    protected function fetchSkpdLoginActions(int $startSkpdId, int $endSkpdId): array
    {
        $listingPath = '/superadmin/skpd';
        $listingResponse = $this->request('GET', $listingPath);
        $listingBody = (string) $listingResponse->getBody();
        $actions = [];

        for ($skpdId = $startSkpdId; $skpdId <= $endSkpdId; $skpdId++) {
            $actions[$skpdId] = $this->extractSkpdLoginAction($listingBody, $skpdId);
            $actions[$skpdId]['listing'] = [
                'path' => $listingPath,
                'status_code' => $listingResponse->getStatusCode(),
                'redirect_history' => $this->redirectHistory($listingResponse),
            ];
        }

        return $actions;
    }

    protected function extractSkpdLoginAction(string $html, int $skpdId): array
    {
        $crawler = $this->createCrawler($html, $this->baseUrl . '/superadmin/skpd');
        $rows = $crawler->filter('table tbody tr');
        $rowIndex = max(0, $skpdId - 1);
        $row = $rows->count() > $rowIndex ? $rows->eq($rowIndex) : null;

        if ($row === null) {
            return [
                'method' => 'GET',
                'url' => $this->skpdLoginPath($skpdId),
                'source' => 'fallback',
            ];
        }

        $form = $this->findSkpdActionForm($row, 'login');
        if ($form->count() > 0) {
            $method = strtoupper(trim((string) $form->attr('method')) ?: 'GET');
            $action = trim((string) $form->attr('action'));
            $formParams = [];

            $form->filter('input[name]')->each(function (Crawler $input) use (&$formParams) {
                $name = trim((string) $input->attr('name'));
                if ($name === '') {
                    return;
                }

                $formParams[$name] = (string) $input->attr('value');
            });

            $submit = $this->findSkpdActionSubmit($form, 'login');
            if ($submit->count() > 0) {
                $submitName = trim((string) $submit->attr('name'));
                if ($submitName !== '') {
                    $formParams[$submitName] = (string) $submit->attr('value');
                }

                $submitAction = trim((string) $submit->attr('formaction'));
                if ($submitAction !== '') {
                    $action = $submitAction;
                }
            }

            return [
                'method' => $method === 'POST' ? 'POST' : 'GET',
                'url' => $action !== '' ? $action : $this->skpdLoginPath($skpdId),
                'form_params' => $formParams,
                'source' => 'login_form',
            ];
        }

        $loginLink = $row->filter('a[href]')->reduce(function (Crawler $link) {
            $text = strtolower($this->normalizeText($link->text('')));

            return $text === 'login' || preg_match('/(^|\s)login(\s|$)/', $text) === 1;
        })->first();

        if ($loginLink->count() > 0) {
            return [
                'method' => 'GET',
                'url' => (string) $loginLink->attr('href'),
                'source' => 'login_link',
            ];
        }

        return [
            'method' => 'GET',
            'url' => $this->skpdLoginPath($skpdId),
            'source' => 'fallback',
        ];
    }

    protected function findSkpdActionForm(Crawler $row, string $actionText): Crawler
    {
        return $row->filter('form')->reduce(function (Crawler $form) use ($actionText) {
            $action = strtolower((string) $form->attr('action'));
            $text = strtolower($this->normalizeText($form->text('')));

            if (str_contains($action, $actionText) || str_contains($text, $actionText)) {
                return true;
            }

            return $this->findSkpdActionSubmit($form, $actionText)->count() > 0;
        })->first();
    }

    protected function findSkpdActionSubmit(Crawler $form, string $actionText): Crawler
    {
        return $form->filter('button, input[type="submit"], input[type="button"]')->reduce(function (Crawler $submit) use ($actionText) {
            $text = strtolower($this->normalizeText($submit->text('')));
            $value = strtolower(trim((string) $submit->attr('value')));
            $title = strtolower(trim((string) $submit->attr('title')));
            $formAction = strtolower(trim((string) $submit->attr('formaction')));
            $class = strtolower(trim((string) $submit->attr('class')));

            return str_contains($text, $actionText)
                || str_contains($value, $actionText)
                || str_contains($title, $actionText)
                || str_contains($formAction, $actionText)
                || preg_match('/(^|[-_\s])' . preg_quote($actionText, '/') . '($|[-_\s])/', $class) === 1;
        })->first();
    }

    protected function logHttpExchange(string $method, string $uri, ResponseInterface $response): void
    {
        Log::debug('Absensi HTTP exchange', [
            'method' => $method,
            'uri' => $uri,
            'status_code' => $response->getStatusCode(),
            'redirect_history' => $this->redirectHistory($response),
        ]);
    }

    protected function requestLogger(): callable
    {
        return function (callable $handler): callable {
            return function (RequestInterface $request, array $options) use ($handler) {
                Log::debug('Absensi traffic request', [
                    'method' => $request->getMethod(),
                    'url' => (string) $request->getUri(),
                    'path' => $request->getUri()->getPath(),
                    'query' => $request->getUri()->getQuery(),
                    'accept' => $request->getHeaderLine('Accept'),
                    'referer' => $request->getHeaderLine('Referer'),
                ]);

                return $handler($request, $options);
            };
        };
    }

    protected function createCrawler(string $html, string $baseUrl): Crawler
    {
        if (class_exists(Crawler::class)) {
            return new Crawler($html, $baseUrl);
        }

        throw new \RuntimeException('Symfony DomCrawler tidak tersedia.');
    }

    protected function extractCsrfTokenFromLoginPage(string $html): ?string
    {
        if (! class_exists(\DOMDocument::class)) {
            return $this->extractHiddenTokenWithRegex($html);
        }

        $previous = libxml_use_internal_errors(true);

        try {
            $dom = new \DOMDocument();
            $dom->loadHTML($html);
            $xpath = new \DOMXPath($dom);
            $nodes = $xpath->query('//input[@type="hidden"][@name="_token"]');

            if ($nodes !== false && $nodes->length > 0) {
                $value = $nodes->item(0)?->getAttribute('value');

                return $value !== '' ? html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8') : null;
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return $this->extractHiddenTokenWithRegex($html);
    }

    protected function extractHiddenTokenWithRegex(string $html): ?string
    {
        $patterns = [
            '/<input\b[^>]*\btype=(["\'])hidden\1[^>]*\bname=(["\'])_token\2[^>]*\bvalue=(["\'])(.*?)\3[^>]*>/is',
            '/<input\b[^>]*\bname=(["\'])_token\1[^>]*\btype=(["\'])hidden\2[^>]*\bvalue=(["\'])(.*?)\3[^>]*>/is',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                return html_entity_decode($matches[4], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }

        return null;
    }

    protected function isLoginPage(string $body): bool
    {
        $normalized = strtolower($body);

        return str_contains($normalized, 'name="username"')
            && str_contains($normalized, 'name="password"')
            && str_contains($normalized, 'pemerintah kota banjarmasin');
    }

    protected function isSkpdListingPage(string $body): bool
    {
        $normalized = strtolower($this->normalizeText(strip_tags($body)));

        return str_contains($normalized, 'kode skpd')
            && str_contains($normalized, 'nama skpd')
            && str_contains($normalized, 'reset pass')
            && str_contains($normalized, 'detail')
            && str_contains($normalized, 'login');
    }

    protected function redirectHistory(ResponseInterface $response): array
    {
        return [
            'locations' => $this->headerValues($response->getHeader('X-Guzzle-Redirect-History')),
            'status_codes' => $this->headerValues($response->getHeader('X-Guzzle-Redirect-Status-History')),
        ];
    }

    protected function headerValues(array $values): array
    {
        return array_values(array_filter(array_map('trim', $values), static fn ($value) => $value !== ''));
    }

    protected function normalizeText(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
    }

    protected function resolveUrl(string $url, string $baseUrl): ?string
    {
        $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($url === '' || str_starts_with($url, 'javascript:') || str_starts_with($url, 'mailto:') || str_starts_with($url, '#')) {
            return null;
        }

        if (preg_match('/^https?:\/\//i', $url)) {
            return $url;
        }

        if (str_starts_with($url, '//')) {
            $scheme = parse_url($this->baseUrl, PHP_URL_SCHEME) ?: 'https';

            return $scheme . ':' . $url;
        }

        try {
            return (string) UriResolver::resolve(new Uri(rtrim($baseUrl, '/')), new Uri($url));
        } catch (Throwable) {
            if (str_starts_with($url, '/')) {
                return rtrim($this->baseUrl, '/') . $url;
            }

            return rtrim($baseUrl, '/') . '/' . ltrim($url, '/');
        }
    }

    protected function preview(string $body, int $limit = 1000): string
    {
        if (function_exists('mb_substr')) {
            return mb_substr($body, 0, $limit);
        }

        return substr($body, 0, $limit);
    }

    protected function defaultHeaders(): array
    {
        return [
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language' => 'en-US,en;q=0.9,id;q=0.8',
            'Cache-Control' => 'no-cache',
            'Pragma' => 'no-cache',
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36',
        ];
    }

    protected function cookieHost(): string
    {
        return parse_url($this->baseUrl, PHP_URL_HOST)
            ?: parse_url(self::DEFAULT_BASE_URL, PHP_URL_HOST)
            ?: 'absensi.banjarmasinkota.go.id';
    }

    protected function restoreCookiesFromLaravelSession(): void
    {
        $storedCookies = $this->sessionGet($this->cookieSessionKey, []);

        if (! is_array($storedCookies)) {
            return;
        }

        foreach ($storedCookies as $cookie) {
            if (! is_array($cookie)) {
                continue;
            }

            $this->cookieJar->setCookie(new SetCookie($cookie));
        }
    }

    protected function syncCookiesToLaravelSession(): void
    {
        $this->sessionPut($this->cookieSessionKey, $this->cookieJar->toArray());
    }

    protected function sessionGet(string $key, mixed $default = null): mixed
    {
        try {
            if (function_exists('session')) {
                return session()->get($key, $default);
            }
        } catch (Throwable) {
            // Session helper may be unavailable in some non-web contexts.
        }

        return $default;
    }

    protected function sessionPut(string $key, mixed $value): void
    {
        try {
            if (function_exists('session')) {
                session()->put($key, $value);
            }
        } catch (Throwable) {
            // Ignore session persistence errors when the session store is not booted.
        }
    }

    protected function configValue(string $key, mixed $default = null): mixed
    {
        try {
            if (function_exists('config')) {
                return config($key, $default);
            }
        } catch (Throwable) {
            // Fall back to the provided default if config is not available.
        }

        return $default;
    }
}
