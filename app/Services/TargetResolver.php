<?php

namespace App\Services;

/**
 * Shared SSRF guard: everything that fetches a user-supplied URL — the scanner
 * and the OpenAPI importer — validates the target here and connects to the
 * address this class approved.
 */
class TargetResolver
{
    /**
     * @return array{scheme: string, host: string, port: int, ip: string}
     *
     * @throws ScanException
     */
    public function resolve(string $url): array
    {
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

        return [
            'scheme' => $scheme,
            'host' => $host,
            'port' => $port,
            'ip' => $this->addressFor($host),
        ];
    }

    /**
     * Options that pin a request to the validated address, so DNS cannot be
     * swapped between our check and the actual connection.
     */
    public function curlOptions(string $host, int $port, string $ip): array
    {
        $maxBytes = config('scanner.max_response_bytes', 5 * 1024 * 1024);

        return [
            CURLOPT_RESOLVE => [sprintf('%s:%d:%s', $host, $port, str_contains($ip, ':') ? "[{$ip}]" : $ip)],
            CURLOPT_MAXFILESIZE => $maxBytes,
            CURLOPT_BUFFERSIZE => 65536,
            CURLOPT_NOPROGRESS => false,
            CURLOPT_PROGRESSFUNCTION => fn ($resource, $downloadSize, $downloaded) => $downloaded > $maxBytes ? 1 : 0,
        ];
    }

    /**
     * @throws ScanException
     */
    private function addressFor(string $host): string
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
}
