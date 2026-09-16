<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ApiScanner;
use App\Services\CertificateInspector;
use App\Services\SecurityScore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SecurityScoreTest extends TestCase
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

        config(['scanner.active_probes' => false]);
    }

    public function test_a_clean_scan_scores_full_marks(): void
    {
        $this->assertSame(100, SecurityScore::calculate([]));
        $this->assertSame('A', SecurityScore::grade(100));
    }

    public function test_severity_drives_the_score(): void
    {
        $this->assertSame(97, SecurityScore::calculate([['severity' => 'low']]));
        $this->assertSame(91, SecurityScore::calculate([['severity' => 'medium']]));
        $this->assertSame(78, SecurityScore::calculate([['severity' => 'high']]));
        $this->assertSame(55, SecurityScore::calculate([['severity' => 'critical']]));
    }

    public function test_one_critical_finding_cannot_leave_a_good_grade(): void
    {
        $score = SecurityScore::calculate([['severity' => 'critical']]);

        $this->assertSame('E', SecurityScore::grade($score));
    }

    public function test_the_score_never_goes_below_zero(): void
    {
        $findings = array_fill(0, 10, ['severity' => 'critical']);

        $this->assertSame(0, SecurityScore::calculate($findings));
        $this->assertSame('F', SecurityScore::grade(0));
    }

    public function test_grade_boundaries(): void
    {
        $this->assertSame('A', SecurityScore::grade(90));
        $this->assertSame('B', SecurityScore::grade(89));
        $this->assertSame('C', SecurityScore::grade(70));
        $this->assertSame('D', SecurityScore::grade(60));
        $this->assertSame('E', SecurityScore::grade(45));
        $this->assertSame('F', SecurityScore::grade(44));
    }

    public function test_a_scan_stores_the_score_on_the_ticket_and_in_history(): void
    {
        Http::fake(['*' => Http::response('{"ok":true}', 200, [
            'Content-Type' => 'application/json',
            'Strict-Transport-Security' => 'max-age=31536000',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; frame-ancestors 'none'",
        ])]);

        $user = User::factory()->create();
        $ticket = $user->tickets()->create(['title' => 'Target', 'api_url' => 'https://8.8.8.8/health']);

        $this->actingAs($user)->post(route('tickets.scan', $ticket));

        $ticket->refresh();
        $this->assertSame(100, $ticket->score);
        $this->assertSame('A', $ticket->grade);
        $this->assertSame(100, $ticket->scans()->first()->score);
    }

    public function test_a_failed_scan_clears_the_score(): void
    {
        Http::fake();

        $user = User::factory()->create();
        $ticket = $user->tickets()->create(['title' => 'Internal', 'api_url' => 'http://127.0.0.1/api', 'score' => 80, 'grade' => 'B']);

        $this->actingAs($user)->post(route('tickets.scan', $ticket));

        $ticket->refresh();
        $this->assertSame('failed', $ticket->status);
        $this->assertNull($ticket->score);
    }

    public function test_the_score_is_visible_on_the_ticket_and_dashboard(): void
    {
        Http::fake(['*' => Http::response('<h1>hai</h1>', 200)]);

        $user = User::factory()->create();
        $ticket = $user->tickets()->create(['title' => 'Legacy', 'api_url' => 'http://8.8.8.8/api']);

        $this->actingAs($user)->post(route('tickets.scan', $ticket));
        $ticket->refresh();

        $this->actingAs($user)->get(route('tickets.show', $ticket))
            ->assertOk()
            ->assertSee('Skor keamanan')
            ->assertSee('Grade '.$ticket->grade);

        $this->actingAs($user)->get(route('tickets.index'))
            ->assertOk()
            ->assertSee('Rata-rata skor', false);
    }

    public function test_rate_limit_headers_count_as_protection(): void
    {
        config(['scanner.active_probes' => true, 'scanner.rate_limit_probe_requests' => 3]);

        Http::fake(['*' => Http::response('{}', 200, ['X-RateLimit-Limit' => '60'])]);

        $report = app(ApiScanner::class)->scan('https://8.8.8.8/api');

        $this->assertNull(
            collect($report['findings'])->firstWhere('title', 'Ada pembatasan jumlah request (rate limit)'),
            'endpoint dengan header kuota tidak boleh dianggap tanpa rate limit'
        );
    }
}
