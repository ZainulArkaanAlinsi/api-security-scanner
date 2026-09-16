<?php

namespace Tests\Feature;

use App\Models\Scan;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompareScansTest extends TestCase
{
    use RefreshDatabase;

    private function check(string $label, bool $passed, string $severity = 'medium'): array
    {
        return [
            'label' => $label,
            'passed' => $passed,
            'severity' => $severity,
            'detail' => $passed ? '' : "Perbaiki {$label}.",
            'category' => 'header',
        ];
    }

    private function scan(Ticket $ticket, int $score, string $grade, array $checks): Scan
    {
        $findings = collect($checks)->reject(fn ($c) => $c['passed'])
            ->map(fn ($c) => ['title' => $c['label'], 'severity' => $c['severity'], 'detail' => $c['detail'], 'category' => $c['category']])
            ->values()->all();

        return $ticket->scans()->create([
            'status' => 'completed',
            'severity' => $findings[0]['severity'] ?? null,
            'findings' => $findings,
            'result' => ['checks' => $checks],
            'score' => $score,
            'grade' => $grade,
        ]);
    }

    /** @return array{0: User, 1: Ticket} */
    private function ticketWithTwoScans(): array
    {
        $user = User::factory()->create();
        $ticket = $user->tickets()->create(['title' => 'Payment API', 'api_url' => 'https://api.example.com/v1', 'status' => 'completed']);

        $this->scan($ticket, 69, 'D', [
            $this->check('Koneksi memakai HTTPS', false, 'high'),
            $this->check('Konfigurasi CORS', false),
            $this->check('Header Content-Security-Policy', true, 'low'),
        ]);

        $this->scan($ticket, 91, 'A', [
            $this->check('Koneksi memakai HTTPS', true, 'high'),
            $this->check('Konfigurasi CORS', false),
            $this->check('Header Content-Security-Policy', false, 'low'),
        ]);

        return [$user, $ticket];
    }

    public function test_it_defaults_to_the_two_most_recent_scans(): void
    {
        [$user, $ticket] = $this->ticketWithTwoScans();

        $this->actingAs($user)->get(route('tickets.compare', $ticket))
            ->assertOk()
            ->assertSee('Bandingkan scan')
            ->assertSee('+22')                    // 69 -> 91
            ->assertSee('Diperbaiki')
            ->assertSee('Memburuk');
    }

    public function test_it_labels_each_check_correctly(): void
    {
        [$user, $ticket] = $this->ticketWithTwoScans();

        $response = $this->actingAs($user)->get(route('tickets.compare', $ticket));

        // HTTPS went from failing to passing, CSP the other way, CORS unchanged.
        $response->assertSeeInOrder(['Konfigurasi CORS', 'Masih gagal']);
        $response->assertSeeInOrder(['Header Content-Security-Policy', 'Memburuk']);
        $response->assertSeeInOrder(['Koneksi memakai HTTPS', 'Diperbaiki']);
    }

    public function test_worse_results_are_listed_first(): void
    {
        [$user, $ticket] = $this->ticketWithTwoScans();

        $this->actingAs($user)->get(route('tickets.compare', $ticket))
            ->assertSeeInOrder(['Memburuk', 'Diperbaiki', 'Masih gagal']);
    }

    public function test_specific_scans_can_be_picked(): void
    {
        [$user, $ticket] = $this->ticketWithTwoScans();
        $third = $this->scan($ticket, 100, 'A', [$this->check('Koneksi memakai HTTPS', true, 'high')]);

        $first = $ticket->scans()->orderBy('id')->first();

        $this->actingAs($user)
            ->get(route('tickets.compare', [$ticket, 'before' => $first->id, 'after' => $third->id]))
            ->assertOk()
            ->assertSee('+31');   // 69 -> 100
    }

    public function test_the_order_of_the_two_scans_does_not_matter(): void
    {
        [$user, $ticket] = $this->ticketWithTwoScans();
        $scans = $ticket->scans()->orderBy('id')->get();

        // Older passed as "after": the page still reads oldest to newest.
        $this->actingAs($user)
            ->get(route('tickets.compare', [$ticket, 'before' => $scans[1]->id, 'after' => $scans[0]->id]))
            ->assertOk()
            ->assertSee('+22');
    }

    public function test_a_check_that_only_exists_in_one_scan_is_marked(): void
    {
        $user = User::factory()->create();
        $ticket = $user->tickets()->create(['title' => 'API', 'api_url' => 'https://api.example.com/', 'status' => 'completed']);

        $this->scan($ticket, 100, 'A', [$this->check('Koneksi memakai HTTPS', true, 'high')]);
        $this->scan($ticket, 97, 'A', [
            $this->check('Koneksi memakai HTTPS', true, 'high'),
            $this->check('Sertifikat TLS berlaku lebih dari 14 hari', true),
        ]);

        $this->actingAs($user)->get(route('tickets.compare', $ticket))
            ->assertOk()
            ->assertSee('Pemeriksaan baru');
    }

    public function test_it_needs_two_successful_scans(): void
    {
        $user = User::factory()->create();
        $ticket = $user->tickets()->create(['title' => 'API', 'api_url' => 'https://api.example.com/', 'status' => 'completed']);
        $this->scan($ticket, 80, 'B', [$this->check('Konfigurasi CORS', true)]);

        $this->actingAs($user)->get(route('tickets.compare', $ticket))
            ->assertRedirect(route('tickets.show', $ticket))
            ->assertSessionHas('error');
    }

    public function test_other_users_cannot_compare_a_ticket(): void
    {
        [, $ticket] = $this->ticketWithTwoScans();

        $this->actingAs(User::factory()->create())
            ->get(route('tickets.compare', $ticket))
            ->assertNotFound();
    }

    public function test_the_ticket_page_links_to_the_comparison(): void
    {
        [$user, $ticket] = $this->ticketWithTwoScans();

        $this->actingAs($user)->get(route('tickets.show', $ticket))
            ->assertOk()
            ->assertSee(route('tickets.compare', $ticket), false);
    }
}
