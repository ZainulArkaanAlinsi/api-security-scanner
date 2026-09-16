<?php

namespace Tests\Feature;

use App\Jobs\ScanTicketJob;
use App\Models\ApiToken;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SharingAndApiTest extends TestCase
{
    use RefreshDatabase;

    private function scannedTicket(User $user): Ticket
    {
        return $user->tickets()->create([
            'title' => 'Payment API',
            'api_url' => 'https://api.example.com/v1',
            'status' => 'completed',
            'severity' => 'medium',
            'score' => 82,
            'grade' => 'B',
            'findings' => [['title' => 'Konfigurasi CORS', 'severity' => 'medium', 'detail' => 'Origin wildcard.', 'category' => 'header']],
            'scan_result' => ['status_code' => 200, 'checks' => [['label' => 'Koneksi memakai HTTPS', 'passed' => true, 'severity' => 'high', 'detail' => '', 'category' => 'transport']]],
            'scanned_at' => now(),
        ]);
    }

    // ------------------------------------------------------------ sharing

    public function test_owner_can_publish_and_unpublish_a_report(): void
    {
        $user = User::factory()->create();
        $ticket = $this->scannedTicket($user);

        $this->actingAs($user)->patch(route('tickets.share', $ticket), ['share' => 1])
            ->assertRedirect(route('tickets.show', $ticket));

        $token = $ticket->fresh()->share_token;
        $this->assertNotNull($token);

        // Anyone with the link, no login at all.
        $this->get(route('tickets.public', $token))
            ->assertOk()
            ->assertSee('Payment API')
            ->assertSee('Konfigurasi CORS')
            ->assertSee('82/100');

        $this->actingAs($user)->patch(route('tickets.share', $ticket), ['share' => 0]);

        $this->assertNull($ticket->fresh()->share_token);
        $this->get(route('tickets.public', $token))->assertNotFound();
    }

    public function test_republishing_issues_a_new_link(): void
    {
        $user = User::factory()->create();
        $ticket = $this->scannedTicket($user);

        $this->actingAs($user)->patch(route('tickets.share', $ticket), ['share' => 1]);
        $first = $ticket->fresh()->share_token;

        $this->actingAs($user)->patch(route('tickets.share', $ticket), ['share' => 0]);
        $this->actingAs($user)->patch(route('tickets.share', $ticket), ['share' => 1]);

        $this->assertNotSame($first, $ticket->fresh()->share_token);
        $this->get(route('tickets.public', $first))->assertNotFound();
    }

    public function test_only_the_owner_can_publish(): void
    {
        $ticket = $this->scannedTicket(User::factory()->create());

        $this->actingAs(User::factory()->create())
            ->patch(route('tickets.share', $ticket), ['share' => 1])
            ->assertNotFound();

        $this->assertNull($ticket->fresh()->share_token);
    }

    public function test_an_unknown_share_token_is_a_404(): void
    {
        $this->get(route('tickets.public', 'tidak-ada'))->assertNotFound();
    }

    // --------------------------------------------------------- api tokens

    public function test_a_token_is_shown_once_and_stored_hashed(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('api-tokens.store'), ['name' => 'GitHub Actions']);

        $plain = session('new_api_token');
        $this->assertStringStartsWith(ApiToken::PREFIX, $plain);

        $stored = $user->apiTokens()->first();
        $this->assertSame(hash('sha256', $plain), $stored->token_hash);
        $this->assertDatabaseMissing('api_tokens', ['token_hash' => $plain]);

        $response->assertRedirect();
    }

    public function test_a_revoked_token_stops_working(): void
    {
        $user = User::factory()->create();
        ['token' => $plain, 'model' => $token] = ApiToken::issue($user, 'CI');

        $this->withToken($plain)->getJson('/api/v1/tickets')->assertOk();

        $this->actingAs($user)->delete(route('api-tokens.destroy', $token));

        $this->withToken($plain)->getJson('/api/v1/tickets')->assertStatus(401);
    }

    public function test_tokens_of_other_users_cannot_be_revoked(): void
    {
        ['model' => $token] = ApiToken::issue(User::factory()->create(), 'CI');

        $this->actingAs(User::factory()->create())
            ->delete(route('api-tokens.destroy', $token))
            ->assertNotFound();

        $this->assertModelExists($token);
    }

    // ----------------------------------------------------------------- api

    public function test_the_api_refuses_requests_without_a_valid_token(): void
    {
        $this->getJson('/api/v1/tickets')->assertStatus(401);
        $this->withToken('apisc_salah')->getJson('/api/v1/tickets')->assertStatus(401);
    }

    public function test_ci_can_queue_a_scan_and_read_the_result(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        ['token' => $plain] = ApiToken::issue($user, 'CI');

        $response = $this->withToken($plain)->postJson('/api/v1/scans', [
            'url' => 'https://api.example.com/v1/health',
        ]);

        $response->assertStatus(202)->assertJsonPath('data.status', 'scanning');
        Queue::assertPushed(ScanTicketJob::class);

        $ticket = $user->tickets()->firstOrFail();
        $ticket->update(['status' => 'completed', 'score' => 91, 'grade' => 'A', 'findings' => [], 'scanned_at' => now()]);

        $this->withToken($plain)->getJson("/api/v1/scans/{$ticket->id}")
            ->assertOk()
            ->assertJsonPath('data.score', 91)
            ->assertJsonPath('data.grade', 'A')
            ->assertJsonPath('data.findings', []);
    }

    public function test_scanning_the_same_url_reuses_the_ticket(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        ['token' => $plain] = ApiToken::issue($user, 'CI');

        $this->withToken($plain)->postJson('/api/v1/scans', ['url' => 'https://api.example.com/v1/health']);
        $user->tickets()->update(['status' => 'completed']);
        $this->withToken($plain)->postJson('/api/v1/scans', ['url' => 'https://api.example.com/v1/health']);

        $this->assertSame(1, $user->tickets()->count());
    }

    public function test_a_running_scan_is_reported_as_a_conflict(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        ['token' => $plain] = ApiToken::issue($user, 'CI');
        $user->tickets()->create(['title' => 'Health', 'api_url' => 'https://api.example.com/v1/health', 'status' => 'scanning']);

        $this->withToken($plain)->postJson('/api/v1/scans', ['url' => 'https://api.example.com/v1/health'])
            ->assertStatus(409);

        Queue::assertNothingPushed();
    }

    public function test_the_api_never_leaks_another_users_ticket(): void
    {
        $owner = User::factory()->create();
        $ticket = $this->scannedTicket($owner);

        ['token' => $plain] = ApiToken::issue(User::factory()->create(), 'CI');

        $this->withToken($plain)->getJson("/api/v1/scans/{$ticket->id}")->assertStatus(404);
        $this->withToken($plain)->getJson('/api/v1/tickets')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_using_a_token_records_when_it_was_last_used(): void
    {
        $user = User::factory()->create();
        ['token' => $plain, 'model' => $token] = ApiToken::issue($user, 'CI');

        $this->assertNull($token->last_used_at);

        $this->withToken($plain)->getJson('/api/v1/tickets')->assertOk();

        $this->assertNotNull($token->fresh()->last_used_at);
    }
}
