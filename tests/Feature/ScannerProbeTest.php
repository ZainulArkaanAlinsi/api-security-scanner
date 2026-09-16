<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ApiScanner;
use App\Services\CertificateInspector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The probes that send extra requests: exposed files, HTTP methods, rate
 * limiting, malformed input, and what the response body gives away.
 */
class ScannerProbeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(CertificateInspector::class, new class extends CertificateInspector
        {
            public function daysUntilExpiry(string $host, string $ip, int $port): ?int
            {
                return null;
            }
        });

        config(['scanner.active_probes' => true, 'scanner.rate_limit_probe_requests' => 3]);
    }

    private function scan(string $url = 'https://8.8.8.8/api'): array
    {
        return app(ApiScanner::class)->scan($url);
    }

    private function finding(array $report, string $title): ?array
    {
        return collect($report['findings'])->firstWhere('title', $title);
    }

    public function test_exposed_env_file_is_reported_as_critical(): void
    {
        Http::fake([
            '*/.env' => Http::response("APP_KEY=base64:abc\nDB_PASSWORD=rahasia", 200),
            '*' => Http::response('{}', 200),
        ]);

        $report = $this->scan();
        $finding = $this->finding($report, 'Tidak ada berkas rahasia yang terekspos');

        $this->assertSame('critical', $finding['severity']);
        $this->assertStringContainsString('/.env', $finding['detail']);
        $this->assertSame('critical', $report['severity'], 'temuan terparah harus menentukan severity ticket');
    }

    public function test_a_catch_all_page_is_not_mistaken_for_a_secret_file(): void
    {
        Http::fake(['*' => Http::response('<html><body>Halaman tidak ditemukan</body></html>', 200)]);

        $report = $this->scan();

        $this->assertNull($this->finding($report, 'Tidak ada berkas rahasia yang terekspos'));
    }

    public function test_dangerous_http_methods_are_reported(): void
    {
        Http::fake(['*' => Http::response('{}', 200, ['Allow' => 'GET, POST, PUT, DELETE, TRACE'])]);

        $finding = $this->finding($this->scan(), 'Tidak ada method HTTP berisiko');

        $this->assertSame('medium', $finding['severity']);
        $this->assertStringContainsString('TRACE', $finding['detail']);
    }

    public function test_missing_rate_limit_is_reported(): void
    {
        Http::fake(['*' => Http::response('{}', 200)]);

        $finding = $this->finding($this->scan(), 'Ada pembatasan jumlah request (rate limit)');

        $this->assertSame('medium', $finding['severity']);
    }

    public function test_sql_error_from_malformed_input_is_high(): void
    {
        Http::fake([
            '*scanner_probe*' => Http::response("SQLSTATE[42000]: Syntax error near ''", 500),
            '*' => Http::response('{}', 200),
        ]);

        $finding = $this->finding($this->scan(), 'Input tidak wajar ditangani dengan aman');

        $this->assertSame('high', $finding['severity']);
        $this->assertStringContainsString('SQL injection', $finding['detail']);
    }

    public function test_sensitive_data_in_the_body_is_reported_without_echoing_it(): void
    {
        Http::fake(['*' => Http::response(
            '{"user":"budi@example.com","token":"eyJhbGciOiJIUzI1NiJ9.eyJzdWIiOiIxMjM0NTY3ODkwIn0.dBjftJeZ4CVPmB92K27uhbUJU1p1r_wW1gFWFOEjXk"}',
            200,
            ['Content-Type' => 'application/json']
        )]);

        $finding = $this->finding($this->scan(), 'Tidak ada data sensitif di respons');

        $this->assertSame('high', $finding['severity']);
        $this->assertStringContainsString('Token JWT', $finding['detail']);
        $this->assertStringContainsString('Alamat email', $finding['detail']);
        $this->assertStringNotContainsString('budi@example.com', $finding['detail'], 'nilai aslinya tidak boleh ikut dilaporkan');
    }

    public function test_open_json_endpoint_is_flagged_as_unauthenticated(): void
    {
        Http::fake(['*' => Http::response(
            '{"users":[{"id":1,"name":"Budi"},{"id":2,"name":"Siti"}]}',
            200,
            ['Content-Type' => 'application/json']
        )]);

        $finding = $this->finding($this->scan(), 'Endpoint tidak membuka data tanpa autentikasi');

        $this->assertSame('medium', $finding['severity']);
    }

    public function test_probe_requests_are_counted_and_bounded(): void
    {
        Http::fake(['*' => Http::response('{}', 200)]);

        $report = $this->scan();

        // 1 main + 6 file probes + 1 OPTIONS + 3 rate-limit + 1 malformed input.
        $this->assertSame(12, $report['result']['requests_sent']);
    }

    public function test_probes_can_be_switched_off(): void
    {
        config(['scanner.active_probes' => false]);
        Http::fake(['*' => Http::response('{}', 200)]);

        $report = $this->scan();

        $this->assertSame(1, $report['result']['requests_sent']);
    }

    public function test_findings_carry_a_category(): void
    {
        Http::fake([
            '*/.env' => Http::response('APP_KEY=base64:abc', 200),
            '*' => Http::response('{}', 200),
        ]);

        $categories = collect($this->scan()['findings'])->pluck('category')->unique();

        $this->assertContains('exposure', $categories);
        $this->assertContains('config', $categories);
    }

    public function test_probe_failures_do_not_break_the_scan(): void
    {
        Http::fake([
            '*/.env' => fn () => throw new ConnectionException('reset'),
            '*' => Http::response('{}', 200),
        ]);

        $report = $this->scan();

        $this->assertIsArray($report['findings']);
        $this->assertSame(200, $report['result']['status_code']);
    }

    public function test_findings_reach_the_ticket_through_a_real_scan(): void
    {
        Http::fake(['*' => Http::response('{}', 200)]);

        $user = User::factory()->create();
        $ticket = $user->tickets()->create(['title' => 'Target', 'api_url' => 'https://8.8.8.8/api']);

        $this->actingAs($user)->post(route('tickets.scan', $ticket));

        $ticket->refresh();
        $this->assertNotEmpty($ticket->findings);
        $this->assertArrayHasKey('category', $ticket->findings[0]);
    }
}
