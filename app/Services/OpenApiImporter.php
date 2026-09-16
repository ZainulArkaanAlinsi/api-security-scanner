<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Reads an OpenAPI 3 or Swagger 2 document and turns every GET endpoint into
 * something the scanner can check.
 */
class OpenApiImporter
{
    public function __construct(private TargetResolver $resolver) {}

    /**
     * @return array<int, array{title: string, url: string}>
     *
     * @throws ScanException
     */
    public function import(string $specUrl): array
    {
        $spec = $this->fetch($specUrl);
        $base = $this->baseUrl($spec, $specUrl);

        $paths = $spec['paths'] ?? null;

        if (! is_array($paths) || $paths === []) {
            throw new ScanException('Dokumen tidak memuat daftar "paths". Pastikan URL mengarah ke file OpenAPI atau Swagger.');
        }

        $limit = (int) config('scanner.import_limit', 50);
        $endpoints = [];

        foreach ($paths as $path => $operations) {
            if (! is_array($operations) || ! isset($operations['get'])) {
                continue;   // the scanner only issues GET requests
            }

            if (count($endpoints) >= $limit) {
                break;
            }

            $summary = $operations['get']['summary'] ?? $operations['get']['operationId'] ?? null;

            $endpoints[] = [
                'title' => trim(($summary ? $summary.' — ' : '').'GET '.$path),
                'url' => rtrim($base, '/').$this->fillPathParameters((string) $path),
            ];
        }

        if ($endpoints === []) {
            throw new ScanException('Tidak ada endpoint GET yang bisa di-scan di dokumen ini.');
        }

        return $endpoints;
    }

    /**
     * @throws ScanException
     */
    private function fetch(string $specUrl): array
    {
        ['host' => $host, 'port' => $port, 'ip' => $ip] = $this->resolver->resolve($specUrl);

        try {
            $response = Http::withOptions([
                'allow_redirects' => false,
                'verify' => config('scanner.ca_bundle') ?: true,
                'curl' => $this->resolver->curlOptions($host, $port, $ip),
            ])
                ->withHeaders(['User-Agent' => 'APIScanner/1.0 (+openapi import)', 'Accept' => 'application/json, application/yaml, text/yaml'])
                ->connectTimeout(5)
                ->timeout(config('scanner.timeout', 10))
                ->get($specUrl);
        } catch (ConnectionException) {
            throw new ScanException("Tidak bisa mengambil dokumen dari {$host}. Pastikan URL benar dan bisa diakses publik.");
        }

        if (! $response->successful()) {
            throw new ScanException("Dokumen tidak bisa diambil (HTTP {$response->status()}).");
        }

        $body = $response->body();
        $parsed = json_decode($body, true);

        if (! is_array($parsed)) {
            try {
                $parsed = Yaml::parse($body);
            } catch (ParseException) {
                $parsed = null;
            }
        }

        if (! is_array($parsed)) {
            throw new ScanException('Isi dokumen bukan JSON atau YAML yang valid.');
        }

        return $parsed;
    }

    /**
     * OpenAPI 3 uses servers[]; Swagger 2 uses schemes + host + basePath.
     *
     * @throws ScanException
     */
    private function baseUrl(array $spec, string $specUrl): string
    {
        $server = $spec['servers'][0]['url'] ?? null;

        if (is_string($server) && $server !== '') {
            // A relative server URL ("/v1") is resolved against the spec itself.
            if (! preg_match('#^https?://#i', $server)) {
                $parts = parse_url($specUrl);
                $server = $parts['scheme'].'://'.$parts['host']
                    .(isset($parts['port']) ? ':'.$parts['port'] : '')
                    .'/'.ltrim($server, '/');
            }

            return $server;
        }

        if (isset($spec['host'])) {
            $scheme = $spec['schemes'][0] ?? 'https';

            return $scheme.'://'.$spec['host'].($spec['basePath'] ?? '');
        }

        // Neither form present: fall back to where the document itself lives.
        $parts = parse_url($specUrl);

        if (! isset($parts['scheme'], $parts['host'])) {
            throw new ScanException('Dokumen tidak menyebutkan alamat server, dan URL dokumen tidak bisa dipakai sebagai acuan.');
        }

        return $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
    }

    /**
     * "/users/{id}/orders" becomes "/users/1/orders" so the request is valid.
     */
    private function fillPathParameters(string $path): string
    {
        return preg_replace('/\{[^}]+\}/', '1', $path);
    }
}
