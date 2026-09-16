<?php

namespace Tests\Feature;

use App\Jobs\ScanTicketJob;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class BadgeAndBulkScanTest extends TestCase
{
    use RefreshDatabase;

    private function sharedTicket(User $user, int $score, string $grade): Ticket
    {
        return $user->tickets()->create([
            'title' => 'Payment API',
            'api_url' => 'https://api.example.com/v1',
            'status' => 'completed',
            'score' => $score,
            'grade' => $grade,
            'findings' => [],
            'scan_result' => ['checks' => []],
            'scanned_at' => now(),
            'share_token' => Str::random(48),
        ]);
    }

    // -------------------------------------------------------------- badge

    public function test_the_badge_renders_the_grade_and_score(): void
    {
        $ticket = $this->sharedTicket(User::factory()->create(), 91, 'A');

        $response = $this->get(route('tickets.badge', $ticket->share_token));

        $response->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml')
            ->assertSee('API security', false)
            ->assertSee('A · 91/100', false);

        $this->assertStringContainsString('<svg', $response->getContent());
    }

    public function test_the_badge_colour_follows_the_score(): void
    {
        $user = User::factory()->create();

        $good = $this->sharedTicket($user, 95, 'A');
        $bad = $user->tickets()->create([
            'title' => 'Legacy', 'api_url' => 'https://legacy.example.com', 'status' => 'completed',
            'score' => 30, 'grade' => 'F', 'findings' => [], 'scan_result' => ['checks' => []],
            'scanned_at' => now(), 'share_token' => Str::random(48),
        ]);

        $this->get(route('tickets.badge', $good->share_token))->assertSee('#16a34a', false);
        $this->get(route('tickets.badge', $bad->share_token))->assertSee('#dc2626', false);
    }

    public function test_the_badge_dies_with_the_share_link(): void
    {
        $user = User::factory()->create();
        $ticket = $this->sharedTicket($user, 80, 'B');
        $token = $ticket->share_token;

        $this->get(route('tickets.badge', $token))->assertOk();

        $this->actingAs($user)->patch(route('tickets.share', $ticket), ['share' => 0]);

        $this->get(route('tickets.badge', $token))->assertNotFound();
    }

    public function test_the_badge_markdown_is_offered_on_the_ticket_page(): void
    {
        $user = User::factory()->create();
        $ticket = $this->sharedTicket($user, 88, 'B');

        $this->actingAs($user)->get(route('tickets.show', $ticket))
            ->assertOk()
            ->assertSee(route('tickets.badge', $ticket->share_token), false);
    }

    // ---------------------------------------------------------- bulk scan

    public function test_scan_all_queues_every_endpoint(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        foreach (range(1, 3) as $i) {
            $user->tickets()->create(['title' => "API {$i}", 'api_url' => "https://api{$i}.example.com/"]);
        }

        $this->actingAs($user)->post(route('tickets.scan-all'))
            ->assertRedirect(route('tickets.index'))
            ->assertSessionHas('success');

        Queue::assertPushed(ScanTicketJob::class, 3);
        $this->assertSame(3, $user->tickets()->where('status', 'scanning')->count());
    }

    public function test_scan_all_skips_endpoints_already_running(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $user->tickets()->create(['title' => 'Idle', 'api_url' => 'https://a.example.com/']);
        $user->tickets()->create(['title' => 'Busy', 'api_url' => 'https://b.example.com/', 'status' => 'scanning']);

        $this->actingAs($user)->post(route('tickets.scan-all'));

        Queue::assertPushed(ScanTicketJob::class, 1);
    }

    public function test_scan_all_never_touches_another_users_endpoints(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $user->tickets()->create(['title' => 'Mine', 'api_url' => 'https://mine.example.com/']);

        $other = User::factory()->create();
        $othersTicket = $other->tickets()->create(['title' => 'Theirs', 'api_url' => 'https://theirs.example.com/']);

        $this->actingAs($user)->post(route('tickets.scan-all'));

        Queue::assertPushed(ScanTicketJob::class, 1);
        $this->assertSame('pending', $othersTicket->fresh()->status);
    }

    public function test_scan_all_says_so_when_there_is_nothing_to_do(): void
    {
        Queue::fake();

        $this->actingAs(User::factory()->create())
            ->post(route('tickets.scan-all'))
            ->assertSessionHas('success', 'Tidak ada endpoint yang perlu di-scan.');

        Queue::assertNothingPushed();
    }
}
