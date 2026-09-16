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

    private array $checks = [];

    public function __construct(private CertificateInspector $certificates)
    {
    }

    /**
     * Scan a URL and return ['severity' => ?string, 'findings' => array, 'result' => array].
     *
     * @throws ScanException
     */
    public function scan(string $url): array
    {
        $this->checks = [];

        $parts = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = isset($parts['host']) ? trim($parts['host'], '[]') : null;

        if (! in_array($scheme, ['http', 'https'], true) || ! $host) {
            throw new ScanException('URL harus diawali http:// atau https:// dan memiliki nama host.');
        }

        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);
        $ip = $this->resolve($host);

        $start = microtime(true);

        try {
            $response = Http::withOptions([
                'allow_redirects' => false,
                'verify' => config('scanner.ca_bundle') ?: true,
                // Pin the connection to the IP we validated so DNS cannot be swapped mid-request.
                'curl' => [CURLOPT_RESOLVE => [sprintf('%s:%d:%s', $host, $port, str_contains($ip, ':') ? "[{$ip}]" : $ip)]],
            ])
                ->withHeaders(['User-Agent' => 'APIScanner/1.0 (+security audit)'])
                ->connectTimeout(5)
                ->timeout(config('scanner.timeout', 10))
                ->get($url);
        } catch (ConnectionException $e) {
            if (preg_match('/cURL error (35|51|58|60)\b/', $e->getMessage())) {
                throw new ScanException("Sertifikat TLS {$host} tidak bisa diverifikasi (kedaluwarsa, self-signed, atau nama domain tidak cocok). Koneksi HTTPS ke endpoint ini tidak aman.");
            }

            throw new ScanException("Tidak bisa terhubung ke {$host}. Pastikan URL benar dan server sedang aktif.");
        }

        $elapsedMs = (int) round((microtime(true) - $start) * 1000);

        $this->runChecks($response, $scheme, $elapsedMs);

        $certificateDays = $scheme === 'https'
            ? $this->certificates->daysUntilExpiry($host, $ip, $port)
            : null;

        if ($certificateDays !== null) {
            $this->check(
                'Sertifikat TLS berlaku lebih dari 14 hari',
                $certificateDays >= 14,
                $certificateDays < 7 ? 'high' : 'medium',
                "Sertifikat habis dalam {$certificateDays} hari. Perpanjang sekarang, karena setelah kedaluwarsa semua klien akan menolak koneksi ke API ini."
            );
        }

        $findings = collect($this->checks)
            ->reject(fn (array $check) => $check['passed'])
            ->map(fn (array $check) => [
                'title' => $check['label'],
                'severity' => $check['severity'],
                'detail' => $check['detail'],
            ])
            ->sortByDesc(fn (array $finding) => self::SEVERITY_RANK[$finding['severity']])
            ->values()
            ->all();

        return [
            'severity' => $this->overallSeverity($findings),
            'findings' => $findings,
            'result' => [
                'status_code' => $response->status(),
                'response_time_ms' => $elapsedMs,
                'ip' => $ip,
                'redirect_to' => $response->redirect() ? $response->header('Location') : null,
                'certificate_days_left' => $certificateDays,
                'headers' => $this->interestingHeaders($response),
                'checks' => $this->checks,
            ],
        ];
    }

    /**
     * Resolve the host and refuse private, loopback and reserved addresses.
     */
    private function resolve(string $host): string
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ips = [$host];
        } else {
            $ips = gethostbynamel($host) ?: [];
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

    private function runChecks(Response $response, string $scheme, int $elapsedMs): void
    {
        $https = $scheme === 'https';

        $this->check(
            'Koneksi memakai HTTPS',
            $https,
            'high',
            'Data dikirim tanpa enkripsi dan bisa disadap atau diubah di tengah jalan. Aktifkan TLS dan paksa semua request ke https://.'
        );

        if ($https) {
            $this->check(
                'Header Strict-Transport-Security',
                $response->hasHeader('Strict-Transport-Security'),
                'medium',
                'Tanpa HSTS, browser masih bisa diarahkan ke versi http. Tambahkan: Strict-Transport-Security: max-age=31536000; includeSubDomains.'
            );
        }

        $this->check(
            'Header X-Content-Type-Options: nosniff',
            strtolower(trim($response->header('X-Content-Type-Options'))) === 'nosniff',
            'low',
            'Browser bisa menebak tipe konten dan mengeksekusi respons sebagai script. Tambahkan: X-Content-Type-Options: nosniff.'
        );

        $csp = $response->header('Content-Security-Policy');

        $this->check(
            'Proteksi clickjacking',
            $response->hasHeader('X-Frame-Options') || str_contains(strtolower($csp), 'frame-ancestors'),
            'low',
            'Halaman bisa disematkan di iframe situs lain. Tambahkan X-Frame-Options: DENY atau CSP frame-ancestors \'none\'.'
        );

        $this->check(
            'Header Content-Security-Policy',
            $csp !== '',
            'low',
            'Tidak ada CSP untuk membatasi sumber script dan konten. Untuk API murni, cukup: Content-Security-Policy: default-src \'none\'.'
        );

        $origin = trim($response->header('Access-Control-Allow-Origin'));
        $credentials = strtolower(trim($response->header('Access-Control-Allow-Credentials'))) === 'true';

        if ($origin === '*' && $credentials) {
            $this->check('Konfigurasi CORS', false, 'high',
                'Access-Control-Allow-Origin: * digabung Allow-Credentials: true. Batasi origin ke domain yang dipercaya.');
        } elseif ($origin === '*') {
            $this->check('Konfigurasi CORS', false, 'medium',
                'API menerima request dari origin mana pun (Access-Control-Allow-Origin: *). Batasi ke domain frontend kamu jika API tidak publik.');
        } else {
            $this->check('Konfigurasi CORS', true, 'medium', '');
        }

        $leaks = collect(['Server', 'X-Powered-By', 'X-AspNet-Version', 'X-AspNetMvc-Version'])
            ->filter(fn (string $name) => preg_match('/\d/', $response->header($name)))
            ->map(fn (string $name) => "{$name}: {$response->header($name)}");

        $this->check(
            'Versi software tidak terekspos',
            $leaks->isEmpty(),
            'low',
            'Header membocorkan versi software ('.$leaks->implode(', ').'). Penyerang bisa mencari celah yang cocok dengan versi itu.'
        );

        $cookies = $response->toPsrResponse()->getHeader('Set-Cookie');
        $weakCookies = collect($cookies)
            ->filter(fn (string $cookie) => ! str_contains(strtolower($cookie), 'httponly')
                || ($https && ! str_contains(strtolower($cookie), 'secure')))
            ->map(fn (string $cookie) => strtok($cookie, '='));

        $this->check(
            'Cookie memakai HttpOnly dan Secure',
            $weakCookies->isEmpty(),
            'medium',
            'Cookie tanpa flag HttpOnly/Secure: '.$weakCookies->implode(', ').'. Cookie bisa dibaca JavaScript atau terkirim lewat koneksi tidak aman.'
        );

        $this->check(
            'Tidak ada error server (5xx)',
            $response->status() < 500,
            'medium',
            "Endpoint mengembalikan status {$response->status()}. Error server bisa menandakan input yang tidak ditangani dengan benar."
        );

        $this->check(
            'Tidak ada stack trace atau pesan debug',
            ! preg_match(self::DEBUG_PATTERN, substr($response->body(), 0, 50000)),
            'high',
            'Respons berisi stack trace atau pesan error internal. Matikan mode debug (misalnya APP_DEBUG=false) di production.'
        );

        $this->check(
            'Waktu respons di bawah 2 detik',
            $elapsedMs < config('scanner.slow_threshold_ms', 2000),
            'low',
            "Respons butuh {$elapsedMs} ms. Endpoint lambat lebih mudah dibuat down dengan banyak request."
        );
    }

    private function check(string $label, bool $passed, string $severity, string $detail): void
    {
        $this->checks[] = [
            'label' => $label,
            'passed' => $passed,
            'severity' => $severity,
            'detail' => $passed ? '' : $detail,
        ];
    }

    private function overallSeverity(array $findings): ?string
    {
        return $findings[0]['severity'] ?? null;
    }

    private function interestingHeaders(Response $response): array
    {
        return collect([
            'Content-Type', 'Server', 'X-Powered-By', 'Strict-Transport-Security',
            'Content-Security-Policy', 'X-Content-Type-Options', 'X-Frame-Options',
            'Access-Control-Allow-Origin', 'Access-Control-Allow-Credentials',
        ])
            ->mapWithKeys(fn (string $name) => [$name => $response->header($name)])
            ->filter()
            ->all();
    }
}
