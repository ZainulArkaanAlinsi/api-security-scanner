<?php

namespace App\Console\Commands;

use App\Jobs\ScanTicketJob;
use App\Models\Ticket;
use Illuminate\Console\Command;

class ScanDueTickets extends Command
{
    protected $signature = 'scan:due {--hours=24 : Minimum hours since the last scan} {--limit=25 : Maximum tickets per run}';

    protected $description = 'Re-scan tickets that have monitoring enabled and were not scanned recently';

    public function handle(): int
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
            $ticket->update(['status' => 'scanning']);

            ScanTicketJob::dispatch($ticket, notify: true);

            $this->line("  [antri]   #{$ticket->id} {$ticket->title}");
        }

        $this->info("{$tickets->count()} ticket masuk antrean. Pastikan queue worker berjalan (php artisan queue:work).");

        return self::SUCCESS;
    }
}
