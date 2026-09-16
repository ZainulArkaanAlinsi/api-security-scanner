<?php

namespace App\Services;

class CertificateInspector
{
    /**
     * Days until the TLS certificate served at host:port expires, or null when
     * it can't be read. Connects to the already-validated IP (no new DNS lookup)
     * and sends the host name via SNI. Trust is verified separately by the HTTP
     * request, so peer verification is skipped here on purpose.
     */
    public function daysUntilExpiry(string $host, string $ip, int $port): ?int
    {
        $context = stream_context_create(['ssl' => [
            'capture_peer_cert' => true,
            'verify_peer' => false,
            'verify_peer_name' => false,
            'SNI_enabled' => true,
            'peer_name' => $host,
        ]]);

        $address = str_contains($ip, ':') ? "[{$ip}]" : $ip;

        $client = @stream_socket_client("ssl://{$address}:{$port}", $errno, $error, 5, STREAM_CLIENT_CONNECT, $context);

        if (! $client) {
            return null;
        }

        $certificate = stream_context_get_params($client)['options']['ssl']['peer_certificate'] ?? null;
        fclose($client);

        $info = $certificate ? openssl_x509_parse($certificate) : false;

        if (! $info || ! isset($info['validTo_time_t'])) {
            return null;
        }

        return (int) floor(($info['validTo_time_t'] - time()) / 86400);
    }
}
