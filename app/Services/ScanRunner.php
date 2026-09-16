<?php

namespace App\Services;

use App\Models\Scan;
use App\Models\Ticket;
use App\Notifications\ScanFindingsNotification;

class ScanRunner
{
    public function __construct(private ApiScanner $scanner)
    {
    }

    /**
     * Run a scan, store it in the ticket history, and refresh the ticket summary.
     * When $notify is true the owner is emailed about high/critical findings that
     * were not in the previous scan — used by automated runs, where nobody is
     * watching the screen.
     */
    public function run(Ticket $ticket, bool $notify = false): Scan
    {
        $previous = $ticket->scans()->where('status', 'completed')->orderByDesc('id')->first();

        $ticket->update(['status' => 'scanning']);

        try {
            $report = $this->scanner->scan($ticket->api_url);
        } catch (ScanException $e) {
            $scan = $ticket->scans()->create([
                'status' => 'failed',
                'error' => $e->getMessage(),
            ]);

            $ticket->update([
                'status' => 'failed',
                'severity' => null,
                'findings' => null,
                'scan_result' => ['error' => $e->getMessage()],
                'scanned_at' => now(),
            ]);

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
            $this->notifyAboutNewRisks($ticket, $scan, $previous);
        }

        return $scan;
    }

    private function notifyAboutNewRisks(Ticket $ticket, Scan $scan, ?Scan $previous): void
    {
        $known = $previous ? $previous->findingTitles() : [];

        $serious = collect($scan->findings ?? [])
            ->whereIn('severity', ['high', 'critical'])
            ->reject(fn (array $finding) => in_array($finding['title'], $known, true))
            ->values();

        if ($serious->isEmpty()) {
            return;
        }

        $ticket->user->notify(new ScanFindingsNotification($ticket, $serious->all()));
    }
}
