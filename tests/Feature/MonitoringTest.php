<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\User;
use App\Notifications\ScanFindingsNotification;
use App\Services\CertificateInspector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class MonitoringTest extends TestCase
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
    }

    private function ticketFor(User $user, array $attributes = []): Ticket
    {
        return $user->tickets()->create(array_merge([
            'title' => 'Orders API',
            'api_url' => 'https://8.8.8.8/orders',
        ], $attributes));
    }

    public function test_owner_can_switch_monitoring_on_and_off(): void
    {
        $user = User::factory()->create();
        $ticket = $this->ticketFor($user);

        $this->actingAs($user)->patch(route('tickets.monitoring', $ticket), ['auto_scan' => 1])
            ->assertRedirect(route('tickets.show', $ticket));
        $this->assertTrue($ticket->fresh()->auto_scan);

        $this->actingAs($user)->patch(route('tickets.monitoring', $ticket), ['auto_scan' => 0]);
        $this->assertFalse($ticket->fresh()->auto_scan);
    }

    public function test_other_users_cannot_switch_monitoring(): void
    {
        $ticket = $this->ticketFor(User::factory()->create());

        $this->actingAs(User::factory()->create())
            ->patch(route('tickets.monitoring', $ticket), ['auto_scan' => 1])
            ->assertNotFound();

        $this->assertFalse($ticket->fresh()->auto_scan);
    }

    public function test_scan_due_only_scans_monitored_tickets_that_are_stale(): void
    {
        Notification::fake();
        Http::fake(['*' => Http::response('{}', 200)]);

        $user = User::factory()->create();
        $due = $this->ticketFor($user, ['title' => 'Due', 'auto_scan' => true, 'scanned_at' => now()->subDays(2)]);
        $never = $this->ticketFor($user, ['title' => 'Never scanned', 'auto_scan' => true]);
        $fresh = $this->ticketFor($user, ['title' => 'Fresh', 'auto_scan' => true, 'scanned_at' => now()->subHour()]);
        $off = $this->ticketFor($user, ['title' => 'Monitoring off', 'scanned_at' => now()->subDays(5)]);

        $this->artisan('scan:due')->assertSuccessful();

        $this->assertSame(1, $due->scans()->count());
        $this->assertSame(1, $never->scans()->count());
        $this->assertSame(0, $fresh->scans()->count());
        $this->assertSame(0, $off->scans()->count());
    }

    public function test_scan_due_emails_owner_about_new_high_findings_only(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $ticket = $this->ticketFor($user, ['auto_scan' => true, 'api_url' => 'http://8.8.8.8/orders']);

        // First automated run: plain HTTP is a new high finding.
        Http::fake(['*' => Http::response('{}', 200)]);
        $this->artisan('scan:due')->assertSuccessful();

        Notification::assertSentTo($user, ScanFindingsNotification::class, function ($notification) {
            return count($notification->findings) === 1
                && $notification->findings[0]['title'] === 'Koneksi memakai HTTPS';
        });

        // Second run with the same result: nothing new, so no second email.
        $ticket->update(['scanned_at' => now()->subDays(2)]);
        $this->artisan('scan:due')->assertSuccessful();

        Notification::assertSentToTimes($user, ScanFindingsNotification::class, 1);
    }

    public function test_failed_automated_scan_does_not_notify(): void
    {
        Notification::fake();
        Http::fake();

        $user = User::factory()->create();
        $this->ticketFor($user, ['api_url' => 'http://127.0.0.1/internal', 'auto_scan' => true]);

        $this->artisan('scan:due')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_csv_export_respects_filters_and_ownership(): void
    {
        $user = User::factory()->create();
        $this->ticketFor($user, ['title' => 'Completed one', 'status' => 'completed', 'severity' => 'high', 'auto_scan' => true]);
        $this->ticketFor($user, ['title' => 'Pending one']);
        $this->ticketFor(User::factory()->create(), ['title' => 'Someone else']);

        $response = $this->actingAs($user)->get(route('tickets.export'));
        $response->assertOk();
        $response->assertDownload('api-scanner-tickets-'.now()->format('Y-m-d').'.csv');

        $csv = $response->streamedContent();
        $this->assertStringContainsString('Completed one', $csv);
        $this->assertStringContainsString('Pending one', $csv);
        $this->assertStringNotContainsString('Someone else', $csv);
        $this->assertStringContainsString('aktif', $csv);

        $filtered = $this->actingAs($user)->get(route('tickets.export', ['status' => 'completed']))->streamedContent();
        $this->assertStringContainsString('Completed one', $filtered);
        $this->assertStringNotContainsString('Pending one', $filtered);
    }

    public function test_dashboard_shows_export_button_and_auto_tag(): void
    {
        $user = User::factory()->create();
        $this->ticketFor($user, ['auto_scan' => true]);

        $this->actingAs($user)->get(route('tickets.index'))
            ->assertOk()
            ->assertSee('Unduh CSV')
            ->assertSee('auto');
    }
}
