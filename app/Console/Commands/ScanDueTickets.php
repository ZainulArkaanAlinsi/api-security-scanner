<?php

namespace App\Console\Commands;

use App\Models\Ticket;
use App\Services\ScanBusyException;
use App\Services\ScanRunner;
use Illuminate\Console\Command;

class ScanDueTickets extends Command
{
    protected $signature = 'scan:due {--hours=24 : Minimum hours since the last scan} {--limit=25 : Maximum tickets per run}';

    protected $description = 'Re-scan tickets that have monitoring enabled and were not scanned recently';

    public function handle(ScanRunner $runner): int
    {
        $cutoff = now()->subHours((int) $this->option('hours'));

        $tickets = Ticket::query()
            ->where('auto_scan', true)
            ->where(fn ($query) => $query->whereNull('scanned_at')->orWhere('scanned_at', '<=', $cutoff))
            ->orderBy('scanned_at')
            ->limit((int) $this->option('limit'))
            ->get();

        if ($tickets->isEmpty()) {
            $this->info('Tidak ada ticket yang perlu di-scan.');

            return self::SUCCESS;
        }

        foreach ($tickets as $ticket) {
            try {
                $scan = $runner->run($ticket, notify: true);
            } catch (ScanBusyException $e) {
                $this->line("  [lewat]   #{$ticket->id} {$ticket->title} — sedang di-scan proses lain");

                continue;
            }

            $this->line(match ($scan->status) {
                'failed' => "  [gagal]   #{$ticket->id} {$ticket->title} — {$scan->error}",
                default => sprintf('  [%s] #%d %s — %d temuan', str_pad($scan->severity ?? 'bersih', 7), $ticket->id, $ticket->title, count($scan->findings ?? [])),
            });
        }

        $this->info("Selesai: {$tickets->count()} ticket di-scan.");

        return self::SUCCESS;
    }
}
