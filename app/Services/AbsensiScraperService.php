<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
use GuzzleHttp\HandlerStack;
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
    private const SENSITIVE_HEADER_KEYWORDS = ['nip', 'nama', 'pegawai', 'nik', 'alamat', 'telepon', 'hp', 'email'];

    private Client $client;
    private CookieJar $cookieJar;
    private string $baseUrl;
    private string $cookieSessionKey;

    public function __construct(?Client $client = null)
    {
        $this->baseUrl = rtrim((string) $this->configValue('services.absensi.base_url', env('ABSENSI_BASE_URL', self::DEFAULT_BASE_URL)), '/');
        $this->cookieSessionKey = (string) $this->configValue('services.absensi.cookie_session_key', env('ABSENSI_COOKIE_SESSION_KEY', self::DEFAULT_COOKIE_SESSION_KEY));

        $host = parse_url($this->baseUrl, PHP_URL_HOST) ?: parse_url(self::DEFAULT_BASE_URL, PHP_URL_HOST) ?: 'absensi.banjarmasinkota.go.id';
        $this->cookieJar = CookieJar::fromArray([], $host);
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
        $page = $this->getCutiPage();

        if (! ($page['success'] ?? false)) {
            return [
                'success' => false,
                'message' => 'Halaman cuti tidak bisa diakses. Login terlebih dahulu.',
                'page' => $page,
            ];
        }

        $body = is_string($page['body'] ?? null) ? (string) $page['body'] : '';
        $parsed = $this->parseCutiHtml($body, $redact);
        $this->saveCutiData($parsed, [
            'path' => self::ADMIN_CUTI_PATH,
            'skpd_id' => $skpdId,
            'fetched_at' => now()->toISOString(),
            'redacted' => $redact,
        ]);

        return [
            'success' => true,
            'page' => [
                'path' => self::ADMIN_CUTI_PATH,
                'status_code' => $page['status_code'],
                'redirect_history' => $page['redirect_history'],
                'body_preview' => $page['body_preview'],
            ],
            'cuti' => $parsed,
        ];
    }

    public function loginAsSkpd(int $skpdId = self::DEFAULT_SKPD_ID): array
    {
        $listingPath = '/superadmin/skpd';
        $listingResponse = $this->request('GET', $listingPath);
        $path = $this->skpdLoginPath($skpdId);
        $response = $this->request('GET', $path, [
            'headers' => [
                'Referer' => $this->baseUrl . $listingPath,
            ],
        ]);
        $body = (string) $response->getBody();

        return [
            'success' => ! $this->isLoginPage($body) && $response->getStatusCode() < 500,
            'listing' => [
                'path' => $listingPath,
                'status_code' => $listingResponse->getStatusCode(),
                'redirect_history' => $this->redirectHistory($listingResponse),
            ],
            'path' => $path,
            'status_code' => $response->getStatusCode(),
            'redirect_history' => $this->redirectHistory($response),
            'body_preview' => $this->preview($body),
        ];
    }

    public function getCutiPage(): array
    {
        $response = $this->request('GET', self::ADMIN_CUTI_PATH);
        $body = (string) $response->getBody();

        return [
            'success' => ! $this->isLoginPage($body) && $response->getStatusCode() === 200,
            'path' => self::ADMIN_CUTI_PATH,
            'status_code' => $response->getStatusCode(),
            'redirect_history' => $this->redirectHistory($response),
            'body' => $body,
            'body_preview' => $this->preview($body),
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
            $row->filter('td')->each(function (Crawler $cell) use (&$values) {
                $values[] = $this->normalizeText($cell->text(''));
            });

            if ($values === []) {
                return;
            }

            $record = [];
            foreach ($values as $index => $value) {
                $key = $headers[$index] ?? 'column_' . ($index + 1);
                $record[$key] = $redact && $this->isSensitiveHeader($key) ? $this->redactValue($value) : $value;
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

        try {
            $response = $this->client->request($method, $uri, $requestOptions);
            $this->syncCookiesToLaravelSession();
            $this->logHttpExchange($method, $uri, $response);

            return $response;
        } catch (Throwable $throwable) {
            Log::error('Absensi request failed', [
                'method' => $method,
                'uri' => $uri,
                'message' => $throwable->getMessage(),
            ]);

            throw $throwable;
        }
    }

    protected function skpdLoginPath(int $skpdId): string
    {
        return '/superadmin/skpd/' . max(1, $skpdId);
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
