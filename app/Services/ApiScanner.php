<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class ApiScanner
{
    private const SEVERITY_RANK = ['low' => 1, 'medium' => 2, 'high' => 3, 'critical' => 4];

    /** Matches common framework error pages and stack traces. */
    private const DEBUG_PATTERN = '/(Stack trace:|Traceback \(most recent call last\)|SQLSTATE\[|Whoops!|Fatal error:|Exception in thread "|at [\w.$]+\([\w]+\.java:\d+\)|<title>.*(Exception|Error).*<\/title>)/i';

    /** Database errors that indicate unsanitised input reaching the query layer. */
    private const SQL_ERROR_PATTERN = '/(SQLSTATE\[|You have an error in your SQL syntax|Unclosed quotation mark|unterminated quoted string|PG::SyntaxError|ORA-\d{5}|SQLiteException|mysqli?_|near "\'": syntax error)/i';

    /**
     * Files that should never be reachable over HTTP, with a signature that
     * proves the real file was served instead of a catch-all 404 page.
     */
    private const EXPOSED_FILES = [
        ['/.env', '/(APP_KEY|DB_PASSWORD|APP_ENV)\s*=/i', 'critical', 'Berkas .env berisi kunci aplikasi dan kredensial database.'],
        ['/.git/config', '/\[core\]|\[remote /i', 'high', 'Direktori .git terbuka memungkinkan seluruh source code diunduh.'],
        ['/phpinfo.php', '/phpinfo\(\)|PHP Version/i', 'high', 'phpinfo membocorkan konfigurasi server, path, dan daftar modul.'],
        ['/actuator/env', '/propertySources|activeProfiles/i', 'high', 'Endpoint actuator Spring Boot membocorkan konfigurasi dan variabel lingkungan.'],
        ['/server-status', '/Apache Server Status|Server Version/i', 'medium', 'Halaman status Apache membocorkan daftar request dan alamat klien.'],
        ['/.DS_Store', '/^\x00\x00\x00\x01Bud1/', 'low', 'Berkas .DS_Store membocorkan struktur direktori di server.'],
    ];

    /** Patterns that should not appear in an API response body. */
    private const SENSITIVE_PATTERNS = [
        ['Private key', '/-----BEGIN (RSA |EC |OPENSSH )?PRIVATE KEY-----/', 'critical'],
        ['AWS access key', '/\bAKIA[0-9A-Z]{16}\b/', 'critical'],
        ['Token JWT', '/\beyJ[A-Za-z0-9_-]{10,}\.[A-Za-z0-9_-]{10,}\.[A-Za-z0-9_-]{10,}\b/', 'high'],
        ['Password dalam respons', '/"(password|passwd|secret|api_?key|access_?token)"\s*:\s*"[^"]{4,}"/i', 'high'],
        ['Alamat email', '/\b[\w.+-]+@[\w-]+\.[\w.]{2,}\b/', 'medium'],
        ['Nomor kartu', '/\b(?:4\d{3}|5[1-5]\d{2}|3[47]\d{2}|6011)[ -]?\d{4}[ -]?\d{4}[ -]?\d{4}\b/', 'high'],
        ['NIK 16 digit', '/\b(1[1-9]|2[1-9]|3[1-9]|5[1-4]|6[1-5]|7[1-6]|8[1-2]|9[1-4])\d{10}\b/', 'medium'],
    ];

    private array $checks = [];

    private int $requests = 0;

    private string $host = '';

    private int $port = 80;

    private string $ip = '';

    public function __construct(private CertificateInspector $certificates) {}

    /**
     * Scan a URL and return ['severity' => ?string, 'findings' => array, 'result' => array].
     *
     * @throws ScanException
     */
    public function scan(string $url): array
    {
        $this->checks = [];
        $this->requests = 0;

        $parts = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = isset($parts['host']) ? trim($parts['host'], '[]') : null;

        if (! in_array($scheme, ['http', 'https'], true) || ! $host) {
            throw new ScanException('URL harus diawali http:// atau https:// dan memiliki nama host.');
        }

        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));

        // Without this the scanner doubles as an anonymous port prober.
        if (! in_array($port, config('scanner.allowed_ports', [80, 443]), true)) {
            throw new ScanException("Port {$port} tidak diizinkan. Scan hanya bisa ke port web standar (80 atau 443).");
        }

        $this->host = $host;
        $this->port = $port;
        $this->ip = $this->resolve($host);

        $start = microtime(true);
        $response = $this->request($url, timeout: config('scanner.timeout', 10));
        $elapsedMs = (int) round((microtime(true) - $start) * 1000);

        $this->checkTransport($response, $scheme, $elapsedMs);
        $this->checkHeaders($response);
        $this->checkCookies($response, $scheme === 'https');
        $this->checkResponseContent($response);

        if (config('scanner.active_probes', true)) {
            $origin = $scheme.'://'.$host.($port === ($scheme === 'https' ? 443 : 80) ? '' : ':'.$port);

            $this->probeExposedFiles($origin);
            $this->probeDangerousMethods($url);
            $this->probeRateLimit($url);
            $this->probeErrorHandling($url);
        }

        $certificateDays = $scheme === 'https'
            ? $this->certificates->daysUntilExpiry($host, $this->ip, $port)
            : null;

        if ($certificateDays !== null) {
            $this->check(
                'Sertifikat TLS berlaku lebih dari 14 hari',
                $certificateDays >= 14,
                $certificateDays < 7 ? 'high' : 'medium',
                "Sertifikat habis dalam {$certificateDays} hari. Perpanjang sekarang, karena setelah kedaluwarsa semua klien akan menolak koneksi ke API ini.",
                'transport'
            );
        }

        $findings = collect($this->checks)
            ->reject(fn (array $check) => $check['passed'])
            ->map(fn (array $check) => [
                'title' => $check['label'],
                'severity' => $check['severity'],
                'detail' => $check['detail'],
                'category' => $check['category'],
            ])
            ->sortByDesc(fn (array $finding) => self::SEVERITY_RANK[$finding['severity']])
            ->values()
            ->all();

        return [
            'severity' => $findings[0]['severity'] ?? null,
            'findings' => $findings,
            'result' => [
                'status_code' => $response->status(),
                'response_time_ms' => $elapsedMs,
                'ip' => $this->ip,
                'redirect_to' => $response->redirect() ? $response->header('Location') : null,
                'certificate_days_left' => $certificateDays,
                'requests_sent' => $this->requests,
                'headers' => $this->interestingHeaders($response),
                'checks' => $this->checks,
            ],
        ];
    }

    // ------------------------------------------------------------------ setup

    /**
     * Resolve the host and refuse private, loopback and reserved addresses.
     */
    private function resolve(string $host): string
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ips = [$host];
        } else {
            $ips = gethostbynamel($host) ?: [];

            // gethostbynamel only returns A records; IPv6-only hosts are valid targets.
            if ($ips === []) {
                $ips = collect(@dns_get_record($host, DNS_AAAA) ?: [])
                    ->pluck('ipv6')
                    ->filter()
                    ->all();
            }
        }

        if ($ips === []) {
            throw new ScanException("Domain {$host} tidak ditemukan.");
        }

        if (! config('scanner.allow_private')) {
            foreach ($ips as $ip) {
                if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    throw new ScanException('Alamat privat atau internal (misalnya localhost, 127.0.0.1, 192.168.x.x) tidak boleh di-scan.');
                }
            }
        }

        return $ips[0];
    }

    /**
     * Every request in a scan goes through here, so the connection stays pinned
     * to the address we validated and the body stays bounded.
     *
     * @throws ScanException
     */
    private function request(string $url, string $method = 'GET', ?int $timeout = null): Response
    {
        $this->requests++;
        $maxBytes = config('scanner.max_response_bytes', 5 * 1024 * 1024);

        try {
            return Http::withOptions([
                'allow_redirects' => false,
                'verify' => config('scanner.ca_bundle') ?: true,
                'curl' => [
                    // Pin the connection to the IP we validated so DNS cannot be swapped mid-request.
                    CURLOPT_RESOLVE => [sprintf('%s:%d:%s', $this->host, $this->port, str_contains($this->ip, ':') ? "[{$this->ip}]" : $this->ip)],
                    // A hostile target must not be able to stream us out of memory.
                    CURLOPT_MAXFILESIZE => $maxBytes,
                    CURLOPT_BUFFERSIZE => 65536,
                    CURLOPT_NOPROGRESS => false,
                    CURLOPT_PROGRESSFUNCTION => fn ($resource, $downloadSize, $downloaded) => $downloaded > $maxBytes ? 1 : 0,
                ],
            ])
                ->withHeaders(['User-Agent' => 'APIScanner/1.0 (+security audit)'])
                ->connectTimeout(5)
                ->timeout($timeout ?? config('scanner.probe_timeout', 5))
                ->send($method, $url);
        } catch (ConnectionException $e) {
            if (preg_match('/cURL error (35|51|58|60)\b/', $e->getMessage())) {
                throw new ScanException("Sertifikat TLS {$this->host} tidak bisa diverifikasi (kedaluwarsa, self-signed, atau nama domain tidak cocok). Koneksi HTTPS ke endpoint ini tidak aman.");
            }

            throw new ScanException("Tidak bisa terhubung ke {$this->host}. Pastikan URL benar dan server sedang aktif.");
        }
    }

    /** A probe must never abort the scan just because one request failed. */
    private function tryRequest(string $url, string $method = 'GET'): ?Response
    {
        try {
            return $this->request($url, $method);
        } catch (ScanException) {
            return null;
        }
    }

    // --------------------------------------------------------- passive checks

    private function checkTransport(Response $response, string $scheme, int $elapsedMs): void
    {
        $https = $scheme === 'https';

        $this->check(
            'Koneksi memakai HTTPS',
            $https,
            'high',
            'Data dikirim tanpa enkripsi dan bisa disadap atau diubah di tengah jalan. Aktifkan TLS dan paksa semua request ke https://.',
            'transport'
        );

        if ($https) {
            $this->check(
                'Header Strict-Transport-Security',
                $response->hasHeader('Strict-Transport-Security'),
                'medium',
                'Tanpa HSTS, browser masih bisa diarahkan ke versi http. Tambahkan: Strict-Transport-Security: max-age=31536000; includeSubDomains.',
                'transport'
            );
        }

        $this->check(
            'Waktu respons di bawah 2 detik',
            $elapsedMs < config('scanner.slow_threshold_ms', 2000),
            'low',
            "Respons butuh {$elapsedMs} ms. Endpoint lambat lebih mudah dibuat down dengan banyak request.",
            'performance'
        );
    }

    private function checkHeaders(Response $response): void
    {
        $this->check(
            'Header X-Content-Type-Options: nosniff',
            strtolower(trim($response->header('X-Content-Type-Options'))) === 'nosniff',
            'low',
            'Browser bisa menebak tipe konten dan mengeksekusi respons sebagai script. Tambahkan: X-Content-Type-Options: nosniff.',
            'header'
        );

        $csp = $response->header('Content-Security-Policy');

        $this->check(
            'Proteksi clickjacking',
            $response->hasHeader('X-Frame-Options') || str_contains(strtolower($csp), 'frame-ancestors'),
            'low',
            'Halaman bisa disematkan di iframe situs lain. Tambahkan X-Frame-Options: DENY atau CSP frame-ancestors \'none\'.',
            'header'
        );

        $this->check(
            'Header Content-Security-Policy',
            $csp !== '',
            'low',
            'Tidak ada CSP untuk membatasi sumber script dan konten. Untuk API murni, cukup: Content-Security-Policy: default-src \'none\'.',
            'header'
        );

        $origin = trim($response->header('Access-Control-Allow-Origin'));
        $credentials = strtolower(trim($response->header('Access-Control-Allow-Credentials'))) === 'true';

        if ($origin === '*' && $credentials) {
            $this->check('Konfigurasi CORS', false, 'high',
                'Access-Control-Allow-Origin: * digabung Allow-Credentials: true. Situs mana pun bisa memakai sesi pengguna kamu. Batasi origin ke domain yang dipercaya.', 'header');
        } elseif ($origin === '*') {
            $this->check('Konfigurasi CORS', false, 'medium',
                'API menerima request dari origin mana pun (Access-Control-Allow-Origin: *). Batasi ke domain frontend kamu jika API tidak publik.', 'header');
        } else {
            $this->check('Konfigurasi CORS', true, 'medium', '', 'header');
        }

        $leaks = collect(['Server', 'X-Powered-By', 'X-AspNet-Version', 'X-AspNetMvc-Version'])
            ->filter(fn (string $name) => preg_match('/\d/', $response->header($name)))
            ->map(fn (string $name) => "{$name}: {$response->header($name)}");

        $this->check(
            'Versi software tidak terekspos',
            $leaks->isEmpty(),
            'low',
            'Header membocorkan versi software ('.$leaks->implode(', ').'). Penyerang bisa mencari celah yang cocok dengan versi itu.',
            'header'
        );
    }

    private function checkCookies(Response $response, bool $https): void
    {
        $cookies = $response->toPsrResponse()->getHeader('Set-Cookie');

        $weak = collect($cookies)
            ->filter(fn (string $cookie) => ! str_contains(strtolower($cookie), 'httponly')
                || ($https && ! str_contains(strtolower($cookie), 'secure')))
            ->map(fn (string $cookie) => strtok($cookie, '='));

        $this->check(
            'Cookie memakai HttpOnly dan Secure',
            $weak->isEmpty(),
            'medium',
            'Cookie tanpa flag HttpOnly/Secure: '.$weak->implode(', ').'. Cookie bisa dibaca JavaScript atau terkirim lewat koneksi tidak aman.',
            'cookie'
        );
    }

    private function checkResponseContent(Response $response): void
    {
        $body = substr($response->body(), 0, 200000);

        $this->check(
            'Tidak ada error server (5xx)',
            $response->status() < 500,
            'medium',
            "Endpoint mengembalikan status {$response->status()}. Error server bisa menandakan input yang tidak ditangani dengan benar.",
            'error-handling'
        );

        $this->check(
            'Tidak ada stack trace atau pesan debug',
            ! preg_match(self::DEBUG_PATTERN, $body),
            'high',
            'Respons berisi stack trace atau pesan error internal. Matikan mode debug (misalnya APP_DEBUG=false) di production.',
            'error-handling'
        );

        // Sensitive values are reported by type only — never echoed back.
        $found = collect(self::SENSITIVE_PATTERNS)
            ->filter(fn (array $rule) => preg_match($rule[1], $body))
            ->values();

        $this->check(
            'Tidak ada data sensitif di respons',
            $found->isEmpty(),
            ($found->max(fn (array $rule) => self::SEVERITY_RANK[$rule[2]]) ?? 0) >= 3 ? 'high' : 'medium',
            'Respons memuat pola data sensitif: '.$found->pluck(0)->implode(', ').'. Pastikan field ini memang boleh publik, atau saring di serializer.',
            'data-exposure'
        );

        $isData = $response->status() === 200
            && str_contains(strtolower($response->header('Content-Type')), 'json')
            && strlen(trim($body)) > 40;

        $this->check(
            'Endpoint tidak membuka data tanpa autentikasi',
            ! $isData,
            'medium',
            'Endpoint mengembalikan data JSON tanpa token atau kredensial apa pun. Kalau data ini seharusnya privat, tambahkan autentikasi.',
            'auth'
        );
    }

    // ---------------------------------------------------------- active probes

    private function probeExposedFiles(string $origin): void
    {
        $exposed = [];
        $worst = 'low';

        foreach (self::EXPOSED_FILES as [$path, $signature, $severity, $detail]) {
            $response = $this->tryRequest($origin.$path);

            if (! $response || $response->status() !== 200) {
                continue;
            }

            if (preg_match($signature, substr($response->body(), 0, 8000))) {
                $exposed[] = ['path' => $path, 'detail' => $detail];

                if (self::SEVERITY_RANK[$severity] > self::SEVERITY_RANK[$worst]) {
                    $worst = $severity;
                }
            }
        }

        $this->check(
            'Tidak ada berkas rahasia yang terekspos',
            $exposed === [],
            $worst,
            'Berkas berikut bisa diunduh siapa saja: '
                .collect($exposed)->map(fn ($item) => $item['path'].' ('.$item['detail'].')')->implode(' '),
            'exposure'
        );
    }

    private function probeDangerousMethods(string $url): void
    {
        $response = $this->tryRequest($url, 'OPTIONS');

        if (! $response) {
            return;
        }

        $allowed = collect(explode(',', $response->header('Allow').','.$response->header('Access-Control-Allow-Methods')))
            ->map(fn (string $method) => strtoupper(trim($method)))
            ->filter()
            ->unique();

        if ($allowed->isEmpty()) {
            return;
        }

        $dangerous = $allowed->intersect(['TRACE', 'TRACK', 'CONNECT', 'PUT', 'DELETE', 'PATCH']);

        $this->check(
            'Tidak ada method HTTP berisiko',
            $dangerous->isEmpty(),
            $dangerous->intersect(['TRACE', 'TRACK', 'CONNECT'])->isNotEmpty() ? 'medium' : 'low',
            'Server mengumumkan method: '.$dangerous->implode(', ').'. Matikan yang tidak dipakai; TRACE memungkinkan serangan Cross-Site Tracing.',
            'config'
        );
    }

    private function probeRateLimit(string $url): void
    {
        $burst = (int) config('scanner.rate_limit_probe_requests', 6);
        $throttled = false;

        for ($i = 0; $i < $burst; $i++) {
            $response = $this->tryRequest($url);

            if (! $response) {
                return;
            }

            if ($response->status() === 429 || $response->hasHeader('Retry-After')) {
                $throttled = true;
                break;
            }
        }

        $this->check(
            'Ada pembatasan jumlah request (rate limit)',
            $throttled,
            'medium',
            "Endpoint melayani {$burst} request beruntun tanpa menolak satu pun. Tanpa rate limit, endpoint rawan brute force dan scraping. Tambahkan throttling plus header Retry-After.",
            'config'
        );
    }

    private function probeErrorHandling(string $url): void
    {
        $separator = str_contains($url, '?') ? '&' : '?';
        $response = $this->tryRequest($url.$separator."scanner_probe='");

        if (! $response) {
            return;
        }

        $body = substr($response->body(), 0, 50000);

        $this->check(
            'Input tidak wajar ditangani dengan aman',
            ! preg_match(self::SQL_ERROR_PATTERN, $body) && ! preg_match(self::DEBUG_PATTERN, $body),
            'high',
            'Mengirim karakter kutip pada parameter membuat server membalas pesan error database atau stack trace. Ini indikasi kuat input tidak divalidasi — periksa kemungkinan SQL injection.',
            'error-handling'
        );
    }

    // ---------------------------------------------------------------- helpers

    private function check(string $label, bool $passed, string $severity, string $detail, string $category): void
    {
        $this->checks[] = [
            'label' => $label,
            'passed' => $passed,
            'severity' => $severity,
            'detail' => $passed ? '' : $detail,
            'category' => $category,
        ];
    }

    private function interestingHeaders(Response $response): array
    {
        return collect([
            'Content-Type', 'Server', 'X-Powered-By', 'Strict-Transport-Security',
            'Content-Security-Policy', 'X-Content-Type-Options', 'X-Frame-Options',
            'Access-Control-Allow-Origin', 'Access-Control-Allow-Credentials', 'Allow',
        ])
            ->mapWithKeys(fn (string $name) => [$name => $response->header($name)])
            ->filter()
            ->all();
    }
}
