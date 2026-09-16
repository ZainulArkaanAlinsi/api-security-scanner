<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatisticsTest extends TestCase
{
    use RefreshDatabase;

    private function scannedTicket(User $user, string $title, int $score, string $grade, array $findings = []): Ticket
    {
        $ticket = $user->tickets()->create([
            'title' => $title,
            'api_url' => 'https://'.str($title)->slug().'.example.com/',
            'status' => 'completed',
            'score' => $score,
            'grade' => $grade,
            'findings' => $findings,
            'scan_result' => ['checks' => []],
            'scanned_at' => now(),
        ]);

        $ticket->scans()->create([
            'status' => 'completed',
            'score' => $score,
            'grade' => $grade,
            'findings' => $findings,
            'result' => ['checks' => []],
        ]);

        return $ticket;
    }

    private function finding(string $title, string $severity, string $category): array
    {
        return ['title' => $title, 'severity' => $severity, 'detail' => '...', 'category' => $category];
    }

    public function test_it_needs_a_login(): void
    {
        $this->get(route('statistics'))->assertRedirect(route('login'));
    }

    public function test_an_empty_account_sees_an_empty_state(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('statistics'))
            ->assertOk()
            ->assertSee('Belum ada data');
    }

    public function test_it_summarises_scores_grades_and_findings(): void
    {
        $user = User::factory()->create();

        $this->scannedTicket($user, 'Payment API', 100, 'A');
        $this->scannedTicket($user, 'Legacy API', 44, 'F', [
            $this->finding('Koneksi memakai HTTPS', 'high', 'transport'),
            $this->finding('Konfigurasi CORS', 'medium', 'header'),
        ]);
        $user->tickets()->create(['title' => 'Belum discan', 'api_url' => 'https://baru.example.com/']);

        $response = $this->actingAs($user)->get(route('statistics'));

        $response->assertOk()
            ->assertSee('72')              // rata-rata (100 + 44) / 2
            ->assertSee('Transport &amp; TLS', false)
            ->assertSee('Header keamanan')
            ->assertSee('Koneksi memakai HTTPS')
            ->assertSee('Legacy API');
    }

    public function test_the_trend_needs_two_different_days(): void
    {
        $user = User::factory()->create();
        $ticket = $this->scannedTicket($user, 'Payment API', 80, 'B');

        $this->actingAs($user)->get(route('statistics'))
            ->assertSee('Butuh scan di minimal dua hari berbeda');

        $old = $ticket->scans()->create(['status' => 'completed', 'score' => 40, 'grade' => 'F', 'findings' => [], 'result' => ['checks' => []]]);
        $old->forceFill(['created_at' => now()->subDays(3)])->save();

        $this->actingAs($user)->get(route('statistics'))
            ->assertSee('<polyline', false)
            ->assertDontSee('Butuh scan di minimal dua hari berbeda');
    }

    public function test_statistics_never_mix_users(): void
    {
        $user = User::factory()->create();
        $this->scannedTicket($user, 'Milik saya', 90, 'A');

        $other = User::factory()->create();
        $this->scannedTicket($other, 'Milik orang lain', 20, 'F', [
            $this->finding('Berkas .env terekspos', 'critical', 'exposure'),
        ]);

        $this->actingAs($user)->get(route('statistics'))
            ->assertOk()
            ->assertSee('Milik saya')
            ->assertDontSee('Milik orang lain')
            ->assertDontSee('Berkas .env terekspos');
    }

    public function test_the_most_common_finding_is_counted_across_endpoints(): void
    {
        $user = User::factory()->create();
        $cors = $this->finding('Konfigurasi CORS', 'medium', 'header');

        $this->scannedTicket($user, 'API satu', 91, 'A', [$cors]);
        $this->scannedTicket($user, 'API dua', 91, 'A', [$cors]);
        $this->scannedTicket($user, 'API tiga', 91, 'A', [$cors]);

        $this->actingAs($user)->get(route('statistics'))
            ->assertOk()
            ->assertSeeInOrder(['Konfigurasi CORS', '3×']);
    }

    public function test_the_navigation_links_to_it(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('tickets.index'))
            ->assertOk()
            ->assertSee(route('statistics'), false);
    }
}
