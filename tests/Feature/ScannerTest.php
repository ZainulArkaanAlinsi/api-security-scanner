<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\User;
use App\Services\CertificateInspector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ScannerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Never open real TLS connections from tests; certificate checks are covered in ScanHistoryTest.
        $this->app->instance(CertificateInspector::class, new class extends CertificateInspector
        {
            public function daysUntilExpiry(string $host, string $ip, int $port): ?int
            {
                return null;
            }
        });
    }

    private function scan(string $url): Ticket
    {
        $user = User::factory()->create();
        $ticket = $user->tickets()->create(['title' => 'Target', 'api_url' => $url]);

        $this->actingAs($user)
            ->post(route('tickets.scan', $ticket))
            ->assertRedirect(route('tickets.show', $ticket));

        return $ticket->fresh();
    }

    public function test_private_and_loopback_targets_are_blocked(): void
    {
        Http::fake();

        foreach (['http://127.0.0.1/admin', 'http://localhost:8080', 'http://192.168.1.10/api', 'http://169.254.169.254/latest/meta-data'] as $url) {
            $ticket = $this->scan($url);

            $this->assertSame('failed', $ticket->status, $url);
            $this->assertStringContainsString('tidak boleh di-scan', $ticket->scan_result['error']);
        }

        Http::assertNothingSent();
    }

    public function test_insecure_endpoint_reports_findings_sorted_by_severity(): void
    {
        Http::fake(['*' => Http::response(
            "<h1>Whoops!</h1>\nStack trace:\n#0 /var/www/app.php(12)",
            500,
            [
                'Server' => 'nginx/1.18.0',
                'Access-Control-Allow-Origin' => '*',
                'Set-Cookie' => 'session=abc; Path=/',
            ]
        )]);

        $ticket = $this->scan('http://8.8.8.8/api');

        $this->assertSame('completed', $ticket->status);
        $this->assertSame('high', $ticket->severity);

        $titles = array_column($ticket->findings, 'title');
        $this->assertContains('Koneksi memakai HTTPS', $titles);
        $this->assertContains('Tidak ada stack trace atau pesan debug', $titles);
        $this->assertContains('Konfigurasi CORS', $titles);
        $this->assertContains('Versi software tidak terekspos', $titles);
        $this->assertContains('Cookie memakai HttpOnly dan Secure', $titles);
        $this->assertContains('Tidak ada error server (5xx)', $titles);
        $this->assertSame('high', $ticket->findings[0]['severity']);
        $this->assertSame(500, $ticket->scan_result['status_code']);

        $user = $ticket->user;
        $this->actingAs($user)->get(route('tickets.show', $ticket))
            ->assertOk()
            ->assertSee('Hasil pemeriksaan')
            ->assertSee('Cetak / simpan PDF');
    }

    public function test_well_configured_endpoint_passes_every_check(): void
    {
        Http::fake(['*' => Http::response('{"ok":true}', 200, [
            'Content-Type' => 'application/json',
            'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; frame-ancestors 'none'",
            'Server' => 'nginx',
            'Set-Cookie' => 'session=abc; Path=/; Secure; HttpOnly',
        ])]);

        $ticket = $this->scan('https://8.8.8.8/health');

        $this->assertSame('completed', $ticket->status);
        $this->assertNull($ticket->severity);
        $this->assertSame([], $ticket->findings);
        $this->assertCount(11, $ticket->scan_result['checks']);
    }

    public function test_wildcard_cors_with_credentials_is_high(): void
    {
        Http::fake(['*' => Http::response('{}', 200, [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Credentials' => 'true',
        ])]);

        $ticket = $this->scan('https://8.8.8.8/');

        $cors = collect($ticket->findings)->firstWhere('title', 'Konfigurasi CORS');
        $this->assertSame('high', $cors['severity']);
    }

    public function test_unreachable_host_marks_ticket_failed(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $ticket = $this->scan('https://8.8.8.8/');

        $this->assertSame('failed', $ticket->status);
        $this->assertStringContainsString('Tidak bisa terhubung', $ticket->scan_result['error']);
    }
}
