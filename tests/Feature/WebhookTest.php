<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CertificateInspector;
use App\Services\WebhookNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SLACK = 'https://hooks.slack.com/services/T000/B000/xyz';

    private const DISCORD = 'https://discord.com/api/webhooks/123/abc';

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

        config(['scanner.active_probes' => false]);
    }

    public function test_slack_and_discord_urls_are_accepted(): void
    {
        $this->assertTrue(WebhookNotifier::isSupported(self::SLACK));
        $this->assertTrue(WebhookNotifier::isSupported(self::DISCORD));
        $this->assertSame('Slack', WebhookNotifier::platform(self::SLACK));
        $this->assertSame('Discord', WebhookNotifier::platform(self::DISCORD));
    }

    public function test_any_other_url_is_refused(): void
    {
        foreach ([
            'http://hooks.slack.com/services/x',        // not https
            'https://evil.example.com/webhook',
            'http://127.0.0.1/internal',
            'https://hooks.slack.com.evil.test/services/x',
        ] as $url) {
            $this->assertFalse(WebhookNotifier::isSupported($url), $url);
        }
    }

    public function test_saving_a_webhook_sends_a_test_message(): void
    {
        Http::fake([self::SLACK => Http::response('ok', 200)]);

        $user = User::factory()->create();

        $this->actingAs($user)->put(route('profile.webhook'), ['webhook_url' => self::SLACK])
            ->assertSessionHas('success');

        $this->assertSame(self::SLACK, $user->fresh()->webhook_url);
        Http::assertSent(fn ($request) => $request->url() === self::SLACK && str_contains($request['text'], 'API Scanner'));
    }

    public function test_an_unsupported_url_is_rejected_without_sending_anything(): void
    {
        Http::fake();

        $user = User::factory()->create();

        $this->actingAs($user)->put(route('profile.webhook'), ['webhook_url' => 'https://evil.example.com/hook'])
            ->assertSessionHasErrorsIn('webhook', 'webhook_url');

        $this->assertNull($user->fresh()->webhook_url);
        Http::assertNothingSent();
    }

    public function test_an_empty_url_switches_notifications_off(): void
    {
        Http::fake();

        $user = User::factory()->create(['webhook_url' => self::SLACK]);

        $this->actingAs($user)->put(route('profile.webhook'), ['webhook_url' => ''])
            ->assertSessionHas('success');

        $this->assertNull($user->fresh()->webhook_url);
    }

    public function test_an_automated_scan_posts_new_high_findings(): void
    {
        Notification::fake();
        Http::fake([
            'hooks.slack.com/*' => Http::response('ok', 200),
            '*' => Http::response('{}', 200),
        ]);

        $user = User::factory()->create(['webhook_url' => self::SLACK]);
        $user->tickets()->create([
            'title' => 'Orders API',
            'api_url' => 'http://8.8.8.8/orders',   // plain HTTP is a high finding
            'auto_scan' => true,
        ]);

        $this->artisan('scan:due')->assertSuccessful();

        Http::assertSent(function ($request) {
            return $request->url() === self::SLACK
                && str_contains($request['text'], 'Orders API')
                && str_contains($request['text'], 'HIGH');
        });
    }

    public function test_a_manual_scan_does_not_post(): void
    {
        Http::fake(['*' => Http::response('{}', 200)]);

        $user = User::factory()->create(['webhook_url' => self::SLACK]);
        $ticket = $user->tickets()->create(['title' => 'Orders API', 'api_url' => 'http://8.8.8.8/orders']);

        $this->actingAs($user)->post(route('tickets.scan', $ticket));

        Http::assertNotSent(fn ($request) => $request->url() === self::SLACK);
    }

    public function test_a_broken_webhook_does_not_break_the_scan(): void
    {
        Notification::fake();
        Http::fake([
            'hooks.slack.com/*' => fn () => throw new ConnectionException('unreachable'),
            '*' => Http::response('{}', 200),
        ]);

        $user = User::factory()->create(['webhook_url' => self::SLACK]);
        $ticket = $user->tickets()->create(['title' => 'Orders API', 'api_url' => 'http://8.8.8.8/orders', 'auto_scan' => true]);

        $this->artisan('scan:due')->assertSuccessful();

        // The scan itself still completed and was recorded.
        $this->assertSame('completed', $ticket->fresh()->status);
        $this->assertSame(1, $ticket->scans()->count());
    }

    public function test_users_without_a_webhook_are_unaffected(): void
    {
        Notification::fake();
        Http::fake(['*' => Http::response('{}', 200)]);

        $user = User::factory()->create(['webhook_url' => null]);
        $user->tickets()->create(['title' => 'Orders API', 'api_url' => 'http://8.8.8.8/orders', 'auto_scan' => true]);

        $this->artisan('scan:due')->assertSuccessful();

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'slack.com'));
    }

    public function test_the_profile_page_shows_the_webhook_field(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Notifikasi Slack / Discord');
    }
}
