<?php

namespace App\Services;

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

class TppScraperService
{
    private const DEFAULT_BASE_URL = 'https://tpp.banjarmasinkota.go.id';
    private const DEFAULT_COOKIE_SESSION_KEY = 'tpp_scraper.cookies';
    private const ANALYSIS_KEYWORDS = ['pegawai', 'presensi', 'tpp', 'skpd'];

    private Client $client;
    private CookieJar $cookieJar;
    private string $baseUrl;
    private string $cookieSessionKey;
    private bool $postLoginTrafficLoggingEnabled = false;

    public function __construct(?Client $client = null)
    {
        $this->baseUrl = rtrim((string) $this->configValue('services.tpp.base_url', env('TPP_BASE_URL', self::DEFAULT_BASE_URL)), '/');
        $this->cookieSessionKey = (string) $this->configValue('services.tpp.cookie_session_key', env('TPP_COOKIE_SESSION_KEY', self::DEFAULT_COOKIE_SESSION_KEY));

        $host = parse_url($this->baseUrl, PHP_URL_HOST) ?: parse_url(self::DEFAULT_BASE_URL, PHP_URL_HOST) ?: 'tpp.banjarmasinkota.go.id';
        $this->cookieJar = CookieJar::fromArray([], $host);
        $this->restoreCookiesFromLaravelSession();

        $stack = HandlerStack::create();
        $stack->push($this->postLoginRequestLogger());

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

    public function login(string $username, string $password): array
    {
        $loginPage = $this->request('GET', '/');
        $html = (string) $loginPage->getBody();

        $csrfToken = $this->extractCsrfTokenFromLoginPage($html);
        $a1 = $this->extractHiddenInputValue($html, 'a1');
        $a2 = $this->extractHiddenInputValue($html, 'a2');

        if ($csrfToken === null || $a1 === null || $a2 === null) {
            throw new \RuntimeException('Tidak menemukan CSRF token atau field captcha pada halaman login.');
        }

        $captchaResult = (string) (((int) $a1) + ((int) $a2));

        $response = $this->request('POST', '/login', [
            'form_params' => [
                '_token' => $csrfToken,
                'username' => $username,
                'password' => $password,
                'a1' => $a1,
                'a2' => $a2,
                'captcha_result' => $captchaResult,
            ],
        ]);

        $this->enablePostLoginTrafficLogging();
        $dashboard = $this->getDashboard();
        $loginBody = (string) $response->getBody();
        $discoveredEndpoints = [];

        if (($dashboard['success'] ?? false) === true) {
            $discoveredEndpoints = $this->discoverEndpoints($dashboard['body'] ?? null, $dashboard['path'] ?? '/');
        }

        $analyzedEndpoints = [];
        if ($discoveredEndpoints !== []) {
            $analyzedEndpoints = $this->analyzeEndpoints();
        }

        $success = (bool) ($dashboard['success'] ?? false);
        if (! $success) {
            $success = ! $this->isLoginPage($loginBody) && $response->getStatusCode() < 400;
        }

        $this->syncCookiesToLaravelSession();

        return [
            'success' => $success,
            'login' => [
                'status_code' => $response->getStatusCode(),
                'redirect_history' => $this->redirectHistory($response),
                'body' => $loginBody,
                'body_preview' => $this->preview($loginBody),
            ],
            'dashboard' => $dashboard,
            'discovered_endpoints' => $discoveredEndpoints,
            'analyzed_endpoints' => $analyzedEndpoints,
            'cookies' => $this->debugCookies(),
        ];
    }

    public function scrape(string $username, string $password): array
    {
        return $this->login($username, $password);
    }

    public function debugCookies(): array
    {
        $cookies = $this->cookieJar->toArray();

        Log::debug('TPP cookie jar', [
            'cookies' => $cookies,
        ]);

        return $cookies;
    }

    public function getDashboard(): array
    {
        $candidates = $this->dashboardCandidates();
        $lastResult = [
            'success' => false,
            'path' => null,
            'status_code' => null,
            'redirect_history' => [],
            'body' => null,
            'body_preview' => null,
        ];

        foreach ($candidates as $path) {
            $response = $this->request('GET', $path);
            $body = (string) $response->getBody();
            $result = [
                'success' => ! $this->isLoginPage($body) && $response->getStatusCode() === 200,
                'path' => $path,
                'status_code' => $response->getStatusCode(),
                'redirect_history' => $this->redirectHistory($response),
                'body' => $body,
                'body_preview' => $this->preview($body),
            ];

            $lastResult = $result;

            if ($result['success']) {
                $this->syncCookiesToLaravelSession();

                return $result;
            }
        }

        return $lastResult;
    }

    public function discoverEndpoints(?string $html = null, ?string $sourcePath = null): array
    {
        $dashboard = null;
        $dashboardUrl = $this->buildAbsoluteUrl($sourcePath ?: '/');

        if ($html === null) {
            $dashboard = $this->getDashboard();
            if (! ($dashboard['success'] ?? false)) {
                return [];
            }

            $html = is_string($dashboard['body'] ?? null) ? (string) $dashboard['body'] : '';
            $sourcePath = is_string($dashboard['path'] ?? null) ? (string) $dashboard['path'] : $sourcePath;
            $dashboardUrl = $this->buildAbsoluteUrl($sourcePath ?: '/');
        }

        if (trim($html) === '') {
            return [];
        }

        $discovered = [];
        $htmlCrawler = $this->createCrawler($html, $dashboardUrl);

        $discovered = array_merge(
            $discovered,
            $this->discoverFromCrawlerNodes($htmlCrawler, 'a[href]', 'link', 'a[href]', $dashboardUrl, 'href'),
            $this->discoverFromCrawlerNodes($htmlCrawler, 'form[action]', 'form', 'form[action]', $dashboardUrl, 'action'),
            $this->discoverFromCrawlerNodes($htmlCrawler, 'script[src]', 'script', 'script[src]', $dashboardUrl, 'src'),
            $this->discoverAjaxEndpointsFromHtml($htmlCrawler, $dashboardUrl)
        );

        $discovered = $this->deduplicateDiscoveredEndpoints($discovered);
        $this->saveDiscoveredEndpoints($discovered, [
            'dashboard_url' => $dashboardUrl,
            'source_path' => $sourcePath,
            'discovered_at' => now()->toISOString(),
        ]);

        return $discovered;
    }

    public function analyzeEndpoints(): array
    {
        $sourcePath = storage_path('scraping/discovered_endpoints.json');
        $targetPath = storage_path('scraping/analyzed_endpoints.json');

        Log::info('TPP endpoint analysis started', [
            'source_path' => $sourcePath,
            'target_path' => $targetPath,
            'cookie_session_key' => $this->cookieSessionKey,
        ]);

        $payload = $this->readJsonFile($sourcePath);
        $endpoints = $this->normalizeDiscoveredEndpointsPayload($payload);

        Log::info('TPP endpoint analysis discovered endpoint list loaded', [
            'source_path' => $sourcePath,
            'count' => count($endpoints),
        ]);

        $results = [];
        $total = count($endpoints);

        foreach ($endpoints as $index => $endpoint) {
            if (! is_array($endpoint)) {
                Log::warning('TPP endpoint analysis skipped invalid entry', [
                    'index' => $index,
                    'reason' => 'endpoint is not an array',
                ]);
                continue;
            }

            $originalUrl = trim((string) ($endpoint['url'] ?? ''));
            if ($originalUrl === '') {
                Log::warning('TPP endpoint analysis skipped endpoint without URL', [
                    'index' => $index,
                    'entry' => $endpoint,
                ]);
                continue;
            }

            $url = $this->resolveUrl($originalUrl, $this->baseUrl) ?? $originalUrl;
            $requestNumber = $index + 1;

            Log::info('TPP endpoint analysis request started', [
                'request_number' => $requestNumber,
                'total' => $total,
                'url' => $url,
                'source' => $endpoint['source'] ?? null,
                'type' => $endpoint['type'] ?? null,
            ]);

            try {
                $response = $this->request('GET', $url, [
                    'headers' => [
                        'Accept' => 'application/json,text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    ],
                ]);

                $body = (string) $response->getBody();
                $status = $response->getStatusCode();
                $contentType = trim($response->getHeaderLine('Content-Type'));
                $responseSize = strlen($body);
                $isJson = $this->isJsonResponse($contentType, $body);
                $recordCount = null;
                $hasTable = false;
                $hasDataTables = false;
                $keywordFound = [];

                if ($isJson) {
                    $decoded = json_decode($body, true);
                    $recordCount = $this->extractJsonRecordCount(is_array($decoded) ? $decoded : null);
                } elseif ($this->isHtmlResponse($contentType, $body)) {
                    $htmlAnalysis = $this->analyzeHtmlResponse($body);
                    $hasTable = $htmlAnalysis['hasTable'];
                    $hasDataTables = $htmlAnalysis['hasDataTables'];
                    $keywordFound = $htmlAnalysis['keywordFound'];
                }

                $result = [
                    'url' => $url,
                    'status' => $status,
                    'contentType' => $contentType,
                    'responseSize' => $responseSize,
                    'isJson' => $isJson,
                    'recordCount' => $recordCount,
                    'hasTable' => $hasTable,
                    'hasDataTables' => $hasDataTables,
                    'keywordFound' => $keywordFound,
                ];

                $results[] = $result;

                Log::info('TPP endpoint analysis completed', [
                    'request_number' => $requestNumber,
                    'total' => $total,
                    'url' => $url,
                    'status' => $status,
                    'content_type' => $contentType,
                    'response_size' => $responseSize,
                    'is_json' => $isJson,
                    'record_count' => $recordCount,
                    'has_table' => $hasTable,
                    'has_data_tables' => $hasDataTables,
                    'keyword_found' => $keywordFound,
                ]);
            } catch (Throwable $throwable) {
                Log::error('TPP endpoint analysis failed', [
                    'request_number' => $requestNumber,
                    'total' => $total,
                    'url' => $url,
                    'message' => $throwable->getMessage(),
                ]);

                $results[] = [
                    'url' => $url,
                    'status' => null,
                    'contentType' => null,
                    'responseSize' => 0,
                    'isJson' => false,
                    'recordCount' => null,
                    'hasTable' => false,
                    'hasDataTables' => false,
                    'keywordFound' => [],
                ];
            }
        }

        $this->saveAnalyzedEndpoints($results, [
            'source_path' => $sourcePath,
            'analyzed_at' => now()->toISOString(),
            'count' => count($results),
            'cookie_session_key' => $this->cookieSessionKey,
        ]);

        Log::info('TPP endpoint analysis finished', [
            'source_path' => $sourcePath,
            'target_path' => $targetPath,
            'count' => count($results),
        ]);

        return $results;
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
            Log::error('TPP request failed', [
                'method' => $method,
                'uri' => $uri,
                'message' => $throwable->getMessage(),
            ]);

            throw $throwable;
        }
    }

    protected function logHttpExchange(string $method, string $uri, ResponseInterface $response): void
    {
        $bodyStream = $response->getBody();
        $bodyPreview = $this->preview((string) $bodyStream);

        if ($bodyStream->isSeekable()) {
            $bodyStream->rewind();
        }

        Log::debug('TPP HTTP exchange', [
            'method' => $method,
            'uri' => $uri,
            'status_code' => $response->getStatusCode(),
            'redirect_history' => $this->redirectHistory($response),
            'body_preview' => $bodyPreview,
        ]);
    }

    protected function createCrawler(string $html, string $baseUrl): Crawler
    {
        if (class_exists(Crawler::class)) {
            return new Crawler($html, $baseUrl);
        }

        throw new \RuntimeException('Symfony DomCrawler tidak tersedia.');
    }

    protected function discoverFromCrawlerNodes(Crawler $crawler, string $selector, string $type, string $source, string $baseUrl, string $attribute): array
    {
        $discovered = [];

        $crawler->filter($selector)->each(function (Crawler $node) use (&$discovered, $attribute, $type, $source, $baseUrl) {
            $value = trim((string) $node->attr($attribute));
            if ($value === '') {
                return;
            }

            $absoluteUrl = $this->resolveUrl($value, $baseUrl);
            if ($absoluteUrl === null || ! $this->matchesEndpointPattern($absoluteUrl)) {
                return;
            }

            $discovered[] = $this->buildEndpointRecord($absoluteUrl, $type, $source, $value);
            $this->logDiscoveredEndpoint($absoluteUrl, $type, $source);
        });

        return $discovered;
    }

    protected function discoverAjaxEndpointsFromHtml(Crawler $crawler, string $baseUrl): array
    {
        $discovered = [];

        $crawler->filter('script')->each(function (Crawler $node) use (&$discovered, $baseUrl) {
            $scriptContent = trim($node->text(''));
            $scriptSrc = trim((string) $node->attr('src'));
            $source = $scriptSrc !== '' ? $this->resolveUrl($scriptSrc, $baseUrl) ?? $scriptSrc : 'inline-script';

            if ($scriptSrc !== '') {
                $scriptUrl = $this->resolveUrl($scriptSrc, $baseUrl);
                if ($scriptUrl !== null) {
                    $scriptBody = $this->fetchScriptBody($scriptUrl);
                    if ($scriptBody !== null) {
                        $discovered = array_merge($discovered, $this->discoverAjaxEndpointsFromText($scriptBody, $scriptUrl, $scriptUrl));
                    }
                }
            }

            if ($scriptContent !== '') {
                $discovered = array_merge($discovered, $this->discoverAjaxEndpointsFromText($scriptContent, $baseUrl, $source));
            }
        });

        return $discovered;
    }

    protected function discoverAjaxEndpointsFromText(string $text, string $baseUrl, string $source): array
    {
        $discovered = [];
        $patterns = $this->ajaxEndpointPatterns();

        foreach ($patterns as $pattern) {
            if (! preg_match_all($pattern, $text, $matches)) {
                continue;
            }

            foreach ($matches[1] as $candidate) {
                $candidate = html_entity_decode(trim($candidate), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $absoluteUrl = $this->resolveUrl($candidate, $baseUrl);

                if ($absoluteUrl === null || ! $this->matchesEndpointPattern($absoluteUrl)) {
                    continue;
                }

                $discovered[] = $this->buildEndpointRecord($absoluteUrl, 'ajax', $source, $candidate);
                $this->logDiscoveredEndpoint($absoluteUrl, 'ajax', $source);
            }
        }

        return $discovered;
    }

    protected function fetchScriptBody(string $scriptUrl): ?string
    {
        try {
            $response = $this->request('GET', $scriptUrl);

            if ($response->getStatusCode() >= 400) {
                return null;
            }

            return (string) $response->getBody();
        } catch (Throwable) {
            return null;
        }
    }

    protected function matchesEndpointPattern(string $url): bool
    {
        $needle = strtolower($url);
        $patterns = ['/api', '/data', '/datatable', '/rekap', '/pegawai', '/presensi', '/tpp', '/json'];

        foreach ($patterns as $pattern) {
            if (str_contains($needle, $pattern)) {
                return true;
            }
        }

        return false;
    }

    protected function ajaxEndpointPatterns(): array
    {
        return [
            '/(?:fetch|axios\.(?:get|post|put|delete|patch)|\$.ajax|\$.get|\$.post)\s*\(\s*[\'"]([^\'"]+)[\'"]/i',
            '/(?:url|endpoint|apiUrl)\s*[:=]\s*[\'"]([^\'"]+)[\'"]/i',
        ];
    }

    protected function buildEndpointRecord(string $url, string $type, string $source, string $matchedValue): array
    {
        return [
            'url' => $url,
            'type' => $type,
            'source' => $source,
            'matched_value' => $matchedValue,
        ];
    }

    protected function logDiscoveredEndpoint(string $url, string $type, string $source): void
    {
        Log::info('TPP endpoint discovered', [
            'url' => $url,
            'type' => $type,
            'source' => $source,
        ]);
    }

    protected function deduplicateDiscoveredEndpoints(array $endpoints): array
    {
        $unique = [];
        $seen = [];

        foreach ($endpoints as $endpoint) {
            if (! is_array($endpoint) || ! isset($endpoint['url'])) {
                continue;
            }

            $key = strtolower((string) $endpoint['url']) . '|' . strtolower((string) ($endpoint['type'] ?? '')) . '|' . strtolower((string) ($endpoint['source'] ?? ''));

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $unique[] = $endpoint;
        }

        return $unique;
    }

    protected function saveDiscoveredEndpoints(array $endpoints, array $meta = []): void
    {
        $targetPath = storage_path('scraping/discovered_endpoints.json');
        $directory = dirname($targetPath);

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $payload = [
            'meta' => $meta,
            'count' => count($endpoints),
            'endpoints' => $endpoints,
        ];

        file_put_contents(
            $targetPath,
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
    }

    protected function saveAnalyzedEndpoints(array $endpoints, array $meta = []): void
    {
        $targetPath = storage_path('scraping/analyzed_endpoints.json');
        $directory = dirname($targetPath);

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        file_put_contents(
            $targetPath,
            json_encode($endpoints, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );

        Log::info('TPP analyzed endpoints saved', [
            'target_path' => $targetPath,
            'count' => count($endpoints),
            'meta' => $meta,
        ]);
    }

    protected function readJsonFile(string $path): array
    {
        if (! file_exists($path)) {
            Log::warning('TPP JSON file missing', [
                'path' => $path,
            ]);

            return [];
        }

        $contents = file_get_contents($path);
        if ($contents === false || trim($contents) === '') {
            Log::warning('TPP JSON file empty', [
                'path' => $path,
            ]);

            return [];
        }

        $decoded = json_decode($contents, true);
        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            Log::warning('TPP JSON file could not be decoded', [
                'path' => $path,
                'json_error' => json_last_error_msg(),
            ]);

            return [];
        }

        return $decoded;
    }

    protected function normalizeDiscoveredEndpointsPayload(array $payload): array
    {
        if (isset($payload['endpoints']) && is_array($payload['endpoints'])) {
            return array_values($payload['endpoints']);
        }

        if (array_is_list($payload)) {
            return array_values($payload);
        }

        return [];
    }

    protected function isJsonResponse(string $contentType, string $body): bool
    {
        $normalizedContentType = strtolower($contentType);

        if ($body === '') {
            return str_contains($normalizedContentType, 'json');
        }

        if (str_contains($normalizedContentType, 'json')) {
            return true;
        }

        json_decode($body, true);

        return json_last_error() === JSON_ERROR_NONE;
    }

    protected function isHtmlResponse(string $contentType, string $body): bool
    {
        $normalizedContentType = strtolower($contentType);
        $normalizedBody = strtolower($body);

        return str_contains($normalizedContentType, 'html')
            || str_contains($normalizedContentType, 'xhtml+xml')
            || str_contains($normalizedBody, '<html')
            || str_contains($normalizedBody, '<table')
            || str_contains($normalizedBody, 'datatable');
    }

    protected function analyzeHtmlResponse(string $body): array
    {
        $normalized = strtolower(html_entity_decode($body, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $keywordFound = [];

        foreach (self::ANALYSIS_KEYWORDS as $keyword) {
            if (str_contains($normalized, $keyword)) {
                $keywordFound[] = $keyword;
            }
        }

        return [
            'hasTable' => str_contains($normalized, '<table') || preg_match('/<table\b/i', $body) === 1,
            'hasDataTables' => str_contains($normalized, 'datatable') || preg_match('/\.datatable\s*\(/i', $body) === 1 || preg_match('/dataTables/i', $body) === 1,
            'keywordFound' => array_values(array_unique($keywordFound)),
        ];
    }

    protected function extractJsonRecordCount(?array $decoded): ?int
    {
        if ($decoded === null) {
            return null;
        }

        if (array_is_list($decoded)) {
            return count($decoded);
        }

        foreach (['data', 'rows', 'result', 'items'] as $field) {
            if (! array_key_exists($field, $decoded)) {
                continue;
            }

            $count = $this->countArrayLikeValue($decoded[$field]);
            if ($count !== null) {
                return $count;
            }
        }

        return null;
    }

    protected function countArrayLikeValue(mixed $value): ?int
    {
        if (! is_array($value)) {
            return null;
        }

        if (array_is_list($value)) {
            return count($value);
        }

        foreach (['data', 'rows', 'result', 'items'] as $field) {
            if (! array_key_exists($field, $value)) {
                continue;
            }

            $nested = $this->countArrayLikeValue($value[$field]);
            if ($nested !== null) {
                return $nested;
            }
        }

        return null;
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
            $resolved = UriResolver::resolve(new Uri(rtrim($baseUrl, '/')), new Uri($url));

            return (string) $resolved;
        } catch (Throwable) {
            if (str_starts_with($url, '/')) {
                return rtrim($this->baseUrl, '/') . $url;
            }

            return rtrim($baseUrl, '/') . '/' . ltrim($url, '/');
        }
    }

    protected function buildAbsoluteUrl(string $path): string
    {
        $path = trim($path);
        if ($path === '' || $path === '/') {
            return $this->baseUrl . '/';
        }

        return $this->resolveUrl($path, $this->baseUrl) ?? $this->baseUrl . '/';
    }

    protected function postLoginRequestLogger(): callable
    {
        return function (callable $handler): callable {
            return function (RequestInterface $request, array $options) use ($handler) {
                if ($this->shouldLogTrafficRequest($request)) {
                    Log::debug('TPP post-login traffic request', [
                        'method' => $request->getMethod(),
                        'url' => (string) $request->getUri(),
                        'path' => $request->getUri()->getPath(),
                        'query' => $request->getUri()->getQuery(),
                        'kind' => $this->classifyTrafficRequest($request),
                        'x_requested_with' => $request->getHeaderLine('X-Requested-With'),
                        'accept' => $request->getHeaderLine('Accept'),
                        'referer' => $request->getHeaderLine('Referer'),
                    ]);
                }

                return $handler($request, $options);
            };
        };
    }

    protected function shouldLogTrafficRequest(RequestInterface $request): bool
    {
        return $this->postLoginTrafficLoggingEnabled && $this->classifyTrafficRequest($request) !== 'page';
    }

    protected function classifyTrafficRequest(RequestInterface $request): string
    {
        $uri = $request->getUri();
        $path = strtolower($uri->getPath());
        $query = strtolower($uri->getQuery());
        $accept = strtolower($request->getHeaderLine('Accept'));
        $xRequestedWith = strtolower($request->getHeaderLine('X-Requested-With'));

        if ($xRequestedWith === 'xmlhttprequest') {
            return 'xhr';
        }

        if (str_contains($path, '/api/') || str_starts_with($path, '/api') || str_contains($query, 'format=json') || str_contains($accept, 'application/json')) {
            return 'api';
        }

        if (
            str_contains($query, 'draw=')
            || str_contains($query, 'columns[')
            || str_contains($query, 'order[')
            || str_contains($query, 'search[')
            || str_contains($query, 'datatable')
            || str_contains($path, 'datatable')
        ) {
            return 'datatables';
        }

        return 'page';
    }

    protected function enablePostLoginTrafficLogging(): void
    {
        $this->postLoginTrafficLoggingEnabled = true;
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

    protected function dashboardCandidates(): array
    {
        $configured = $this->configValue('services.tpp.dashboard_path', env('TPP_DASHBOARD_PATH'));
        $candidates = [
            $this->normalizePath(is_string($configured) ? $configured : ''),
            '/',
            '/dashboard',
            '/home',
            '/beranda',
        ];

        return array_values(array_unique(array_filter($candidates, static fn ($path) => $path !== '')));
    }

    protected function normalizePath(string $path): string
    {
        $path = trim($path);

        if ($path === '') {
            return '';
        }

        return str_starts_with($path, '/') ? $path : '/' . $path;
    }

    protected function isLoginPage(string $body): bool
    {
        $normalized = strtolower($body);

        return str_contains($normalized, 'login tpp')
            || (str_contains($normalized, 'name="username"') && str_contains($normalized, 'name="password"') && str_contains($normalized, 'name="captcha_result"'));
    }

    protected function extractHiddenInputValue(string $html, string $name): ?string
    {
        if (class_exists(\DOMDocument::class)) {
            $previous = libxml_use_internal_errors(true);

            try {
                $dom = new \DOMDocument();
                $dom->loadHTML($html);
                $xpath = new \DOMXPath($dom);
                $nodes = $xpath->query(sprintf('//input[@name="%s"]', $name));

                if ($nodes !== false && $nodes->length > 0) {
                    $value = $nodes->item(0)?->getAttribute('value');

                    return $value !== '' ? html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8') : null;
                }
            } finally {
                libxml_clear_errors();
                libxml_use_internal_errors($previous);
            }
        }

        $pattern = sprintf('/<input\b[^>]*\bname=(["\'])%s\1[^>]*\bvalue=(["\'])(.*?)\2[^>]*>/is', preg_quote($name, '/'));

        if (! preg_match($pattern, $html, $matches)) {
            return null;
        }

        return html_entity_decode($matches[3], ENT_QUOTES | ENT_HTML5, 'UTF-8');
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
