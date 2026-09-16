<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Posts scan results to a Slack or Discord incoming webhook.
 *
 * Only the two vendors' own hosts are accepted: this endpoint sends requests
 * on behalf of a user, so an open URL field would be an SSRF hole.
 */
class WebhookNotifier
{
    public const SLACK_PATTERN = '#^https://hooks\.slack\.com/services/#';

    public const DISCORD_PATTERN = '#^https://(canary\.|ptb\.)?discord(app)?\.com/api/webhooks/#';

    public static function isSupported(string $url): bool
    {
        return (bool) (preg_match(self::SLACK_PATTERN, $url) || preg_match(self::DISCORD_PATTERN, $url));
    }

    public static function platform(string $url): string
    {
        return preg_match(self::DISCORD_PATTERN, $url) ? 'Discord' : 'Slack';
    }

    /**
     * @param  array<int, array{title: string, severity: string, detail: string}>  $findings
     */
    public function notify(User $user, Ticket $ticket, array $findings): bool
    {
        if (! $user->webhook_url || ! self::isSupported($user->webhook_url)) {
            return false;
        }

        $count = count($findings);
        $lines = collect($findings)
            ->map(fn (array $f) => '• *'.strtoupper($f['severity']).'* — '.$f['title'])
            ->implode("\n");

        $text = "*{$count} temuan berisiko tinggi di {$ticket->title}*\n"
            ."{$ticket->api_url}\n"
            .($ticket->grade ? "Skor {$ticket->score}/100 (grade {$ticket->grade})\n" : '')
            ."\n{$lines}\n\n"
            .route('tickets.show', $ticket);

        return $this->post($user->webhook_url, $text);
    }

    public function test(User $user): bool
    {
        return $this->post(
            $user->webhook_url ?? '',
            "*API Scanner* terhubung.\nKamu akan menerima pesan di sini saat scan otomatis menemukan temuan high atau critical yang baru."
        );
    }

    private function post(string $url, string $text): bool
    {
        if (! self::isSupported($url)) {
            return false;
        }

        // Slack reads "text", Discord reads "content" — sending both keeps one payload.
        $payload = ['text' => $text, 'content' => $text];

        try {
            $response = Http::timeout(8)->asJson()->post($url, $payload);

            if ($response->failed()) {
                Log::warning('Webhook ditolak', ['status' => $response->status()]);
            }

            return $response->successful();
        } catch (Throwable $e) {
            // A broken webhook must never fail the scan that triggered it.
            Log::warning('Webhook gagal dikirim', ['exception' => $e->getMessage()]);

            return false;
        }
    }
}
