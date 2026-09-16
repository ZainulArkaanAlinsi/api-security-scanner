<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\User;
use App\Services\CertificateInspector;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ScanHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(CertificateInspector::class, new class extends CertificateInspector {
            public ?int $days = 90;

            public function daysUntilExpiry(string $host, string $ip, int $port): ?int
            {
                return $this->days;
            }
        });
    }

    private function ticket(): array
    {
        $user = User::factory()->create();
        $ticket = $user->tickets()->create(['title' => 'Target', 'api_url' => 'https://8.8.8.8/api']);

        return [$user, $ticket];
    }

    private function runScan(User $user, Ticket $ticket): void
    {
        $this->actingAs($user)->post(route('tickets.scan', $ticket))->assertRedirect(route('tickets.show', $ticket));
    }

    public function test_every_scan_is_kept_and_changes_are_shown(): void
    {
        [$user, $ticket] = $this->ticket();

        Http::fake(['*' => Http::sequence()
            // First scan: CORS wildcard and no nosniff.
            ->push('{}', 200, [
                'Access-Control-Allow-Origin' => '*',
                'Strict-Transport-Security' => 'max-age=31536000',
                'Content-Security-Policy' => "default-src 'none'; frame-ancestors 'none'",
            ])
            // Second scan: CORS fixed, nosniff still missing, version leak is new.
            ->push('{}', 200, [
                'Server' => 'Apache/2.4.1',
                'Strict-Transport-Security' => 'max-age=31536000',
                'Content-Security-Policy' => "default-src 'none'; frame-ancestors 'none'",
            ]),
        ]);

        $this->runScan($user, $ticket);
        $this->runScan($user, $ticket);

        $this->assertSame(2, $ticket->scans()->count());

        $this->actingAs($user)->get(route('tickets.show', $ticket))
            ->assertOk()
            ->assertSee('Perubahan sejak scan sebelumnya')
            ->assertSeeInOrder(['Sudah diperbaiki (1)', 'Konfigurasi CORS'])
            ->assertSeeInOrder(['Temuan baru (1)', 'Versi software tidak terekspos'])
            ->assertSee('Riwayat scan');
    }

    public function test_failed_scans_are_recorded_in_history(): void
    {
        [$user] = $this->ticket();
        $ticket = $user->tickets()->create(['title' => 'Local', 'api_url' => 'http://127.0.0.1/']);

        $this->runScan($user, $ticket);

        $scan = $ticket->scans()->first();
        $this->assertSame('failed', $scan->status);
        $this->assertStringContainsString('tidak boleh di-scan', $scan->error);
    }

    public function test_expiring_certificate_is_reported(): void
    {
        [$user, $ticket] = $this->ticket();
        $this->app->make(CertificateInspector::class)->days = 5;

        Http::fake(['*' => Http::response('{}', 200)]);
        $this->runScan($user, $ticket);

        $finding = collect($ticket->fresh()->findings)->firstWhere('title', 'Sertifikat TLS berlaku lebih dari 14 hari');
        $this->assertSame('high', $finding['severity']);
        $this->assertSame(5, $ticket->fresh()->scan_result['certificate_days_left']);
    }

    public function test_changing_url_clears_history(): void
    {
        [$user, $ticket] = $this->ticket();
        Http::fake(['*' => Http::response('{}', 200)]);
        $this->runScan($user, $ticket);

        $this->actingAs($user)->put(route('tickets.update', $ticket), [
            'title' => 'Target',
            'api_url' => 'https://8.8.4.4/other',
        ]);

        $this->assertSame(0, $ticket->scans()->count());
    }

    public function test_printable_report_requires_completed_scan_and_owner(): void
    {
        [$user, $ticket] = $this->ticket();

        $this->actingAs($user)->get(route('tickets.print', $ticket))->assertRedirect(route('tickets.show', $ticket));

        Http::fake(['*' => Http::response('{}', 200, ['Access-Control-Allow-Origin' => '*'])]);
        $this->runScan($user, $ticket);

        $this->actingAs($user)->get(route('tickets.print', $ticket))
            ->assertOk()
            ->assertSee('Laporan audit keamanan API')
            ->assertSee('Konfigurasi CORS');

        $this->actingAs(User::factory()->create())->get(route('tickets.print', $ticket))->assertNotFound();
    }

    public function test_demo_seeder_builds_dashboard_ready_data(): void
    {
        $this->seed(DemoSeeder::class);
        $this->seed(DemoSeeder::class); // idempotent

        $demo = User::firstWhere('email', 'demo@example.com');
        $this->assertSame(4, $demo->tickets()->count());
        $this->assertSame(5, $demo->tickets()->withCount('scans')->get()->sum('scans_count'));

        $payment = $demo->tickets()->firstWhere('title', 'Payment API production');
        $this->actingAs($demo)->get(route('tickets.show', $payment))
            ->assertOk()
            ->assertSee('Sudah diperbaiki (1)');

        $this->actingAs($demo)->get(route('tickets.index'))->assertOk()->assertSee('Legacy partner API');
    }
}
