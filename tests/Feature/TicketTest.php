<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketTest extends TestCase
{
    use RefreshDatabase;

    private function ticketFor(User $user, array $attributes = []): Ticket
    {
        return $user->tickets()->create(array_merge([
            'title' => 'Payment API',
            'api_url' => 'https://api.example.com/v1',
        ], $attributes));
    }

    public function test_dashboard_renders_empty_state(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('tickets.index'))
            ->assertOk()
            ->assertSee('Belum ada ticket');
    }

    public function test_user_can_create_ticket_owned_by_them(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('tickets.create'))->assertOk();

        $response = $this->actingAs($user)->post(route('tickets.store'), [
            'title' => 'Orders API',
            'api_url' => 'https://api.example.com/orders',
            'description' => 'Production',
        ]);

        $ticket = Ticket::firstOrFail();
        $response->assertRedirect(route('tickets.show', $ticket));
        $this->assertSame($user->id, $ticket->user_id);
        $this->assertSame('pending', $ticket->fresh()->status);
    }

    public function test_ticket_requires_http_url(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('tickets.store'), ['title' => 'Bad', 'api_url' => 'ftp://example.com'])
            ->assertSessionHasErrors('api_url');
    }

    public function test_dashboard_only_lists_own_tickets_and_filters(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->ticketFor($user, ['title' => 'Mine completed', 'status' => 'completed', 'severity' => 'high']);
        $this->ticketFor($user, ['title' => 'Mine pending']);
        $this->ticketFor($other, ['title' => 'Someone else']);

        $this->actingAs($user)->get(route('tickets.index'))
            ->assertOk()
            ->assertSee('Mine completed')
            ->assertSee('Mine pending')
            ->assertDontSee('Someone else');

        $this->actingAs($user)->get(route('tickets.index', ['status' => 'completed']))
            ->assertSee('Mine completed')
            ->assertDontSee('Mine pending');

        $this->actingAs($user)->get(route('tickets.index', ['q' => 'pending']))
            ->assertSee('Mine pending')
            ->assertDontSee('Mine completed');
    }

    public function test_user_cannot_access_other_users_ticket(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $ticket = $this->ticketFor($owner);

        $this->actingAs($intruder)->get(route('tickets.show', $ticket))->assertNotFound();
        $this->actingAs($intruder)->get(route('tickets.edit', $ticket))->assertNotFound();
        $this->actingAs($intruder)->put(route('tickets.update', $ticket), ['title' => 'x', 'api_url' => 'https://evil.test'])->assertNotFound();
        $this->actingAs($intruder)->post(route('tickets.scan', $ticket))->assertNotFound();
        $this->actingAs($intruder)->get(route('tickets.report', $ticket))->assertNotFound();
        $this->actingAs($intruder)->delete(route('tickets.destroy', $ticket))->assertNotFound();

        $this->assertModelExists($ticket);
    }

    public function test_changing_url_clears_previous_scan(): void
    {
        $user = User::factory()->create();
        $ticket = $this->ticketFor($user, [
            'status' => 'completed',
            'severity' => 'high',
            'findings' => [['title' => 'x', 'severity' => 'high', 'detail' => 'y']],
            'scan_result' => ['status_code' => 200],
            'scanned_at' => now(),
        ]);

        $this->actingAs($user)->put(route('tickets.update', $ticket), [
            'title' => 'Payment API',
            'api_url' => 'https://api.example.com/v2',
        ])->assertRedirect(route('tickets.show', $ticket));

        $ticket->refresh();
        $this->assertSame('pending', $ticket->status);
        $this->assertNull($ticket->severity);
        $this->assertNull($ticket->scanned_at);
    }

    public function test_owner_can_view_edit_and_delete(): void
    {
        $user = User::factory()->create();
        $ticket = $this->ticketFor($user);

        $this->actingAs($user)->get(route('tickets.show', $ticket))->assertOk()->assertSee('Belum pernah di-scan');
        $this->actingAs($user)->get(route('tickets.edit', $ticket))->assertOk();
        $this->actingAs($user)->delete(route('tickets.destroy', $ticket))->assertRedirect(route('tickets.index'));

        $this->assertModelMissing($ticket);
    }

    public function test_report_requires_a_completed_scan_and_downloads_json(): void
    {
        $user = User::factory()->create();
        $ticket = $this->ticketFor($user);

        $this->actingAs($user)->get(route('tickets.report', $ticket))
            ->assertRedirect(route('tickets.show', $ticket));

        $ticket->update([
            'status' => 'completed',
            'severity' => 'low',
            'findings' => [['title' => 'CSP', 'severity' => 'low', 'detail' => 'missing']],
            'scan_result' => ['status_code' => 200, 'checks' => []],
            'scanned_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('tickets.report', $ticket));
        $response->assertOk();
        $response->assertDownload("scan-report-{$ticket->id}.json");

        $json = json_decode($response->streamedContent(), true);
        $this->assertSame('low', $json['severity']);
        $this->assertSame('CSP', $json['findings'][0]['title']);
    }

    public function test_unknown_ticket_shows_custom_404(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/tickets/999999')
            ->assertNotFound()
            ->assertSee('Halaman tidak ditemukan');
    }
}
