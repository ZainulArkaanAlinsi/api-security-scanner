<?php

namespace App\Services;

use App\Models\Scan;
use App\Models\Ticket;
use App\Notifications\ScanFindingsNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class ScanRunner
{
    /** A scan may not hold the lock longer than this. */
    private const LOCK_SECONDS = 120;

    public function __construct(private ApiScanner $scanner) {}

    /**
     * Run a scan, store it in the ticket history, and refresh the ticket summary.
     * When $notify is true the owner is emailed about high/critical findings that
     * were not in the previous scan — used by automated runs, where nobody is
     * watching the screen.
     *
     * @throws ScanBusyException when the same ticket is already being scanned
     */
    public function run(Ticket $ticket, bool $notify = false): Scan
    {
        $lock = Cache::lock("scan-ticket-{$ticket->id}", self::LOCK_SECONDS);

        if (! $lock->get()) {
            throw new ScanBusyException('Ticket ini sedang di-scan. Tunggu sampai scan yang berjalan selesai.');
        }

        try {
            return $this->perform($ticket, $notify);
        } catch (Throwable $e) {
            // Never leave a ticket stuck on "scanning" when something unexpected
            // breaks mid-run: a database error, a killed worker, or a bug.
            $this->recordFailure($ticket, 'Scan berhenti karena kesalahan tak terduga. Silakan coba lagi.');

            Log::error('Scan crashed', ['ticket_id' => $ticket->id, 'exception' => $e]);

            throw $e;
        } finally {
            $lock->release();
        }
    }

    private function perform(Ticket $ticket, bool $notify): Scan
    {
        $ticket->update(['status' => 'scanning']);

        try {
            $report = $this->scanner->scan($ticket->api_url);
        } catch (ScanException $e) {
            $scan = $ticket->scans()->create([
                'status' => 'failed',
                'error' => $e->getMessage(),
            ]);

            $this->recordFailure($ticket, $e->getMessage());

            return $scan;
        }

        $scan = $ticket->scans()->create([
            'status' => 'completed',
            'severity' => $report['severity'],
            'findings' => $report['findings'],
            'result' => $report['result'],
        ]);

        $ticket->update([
            'status' => 'completed',
            'severity' => $report['severity'],
            'findings' => $report['findings'],
            'scan_result' => $report['result'],
            'scanned_at' => now(),
        ]);

        if ($notify) {
            $this->notifyAboutNewRisks($ticket, $scan);
        }

        return $scan;
    }

    private function recordFailure(Ticket $ticket, string $message): void
    {
        $ticket->update([
            'status' => 'failed',
            'severity' => null,
            'findings' => null,
            'scan_result' => ['error' => $message],
            'scanned_at' => now(),
        ]);
    }

    private function notifyAboutNewRisks(Ticket $ticket, Scan $scan): void
    {
        $serious = collect($scan->findings ?? [])->whereIn('severity', ['high', 'critical']);

        if ($serious->isEmpty()) {
            return;
        }

        $previous = $ticket->scans()
            ->where('status', 'completed')
            ->where('id', '<', $scan->id)
            ->orderByDesc('id')
            ->first();

        $known = $previous ? $previous->findingTitles() : [];

        $new = $serious->reject(fn (array $finding) => in_array($finding['title'], $known, true))->values();

        if ($new->isEmpty()) {
            return;
        }

        $ticket->user->notify(new ScanFindingsNotification($ticket, $new->all()));
    }
}
