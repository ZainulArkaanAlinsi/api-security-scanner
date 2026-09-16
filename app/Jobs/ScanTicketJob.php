<?php

namespace App\Jobs;

use App\Models\Ticket;
use App\Services\ScanBusyException;
use App\Services\ScanRunner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ScanTicketJob implements ShouldQueue
{
    use Queueable;

    /** Long enough for a slow endpoint plus the TLS probe. */
    public int $timeout = 60;

    public int $tries = 1;

    public function __construct(public Ticket $ticket, public bool $notify = false) {}

    public function handle(ScanRunner $runner): void
    {
        try {
            $runner->run($this->ticket, $this->notify);
        } catch (ScanBusyException) {
            // Another worker already holds this ticket; nothing to do.
        }
    }

    /**
     * The worker was killed or the job exhausted its timeout: make sure the
     * ticket does not stay on "scanning" forever.
     */
    public function failed(?Throwable $e): void
    {
        $this->ticket->refresh();

        if ($this->ticket->status !== 'scanning') {
            return;
        }

        $this->ticket->update([
            'status' => 'failed',
            'scan_result' => ['error' => 'Scan tidak selesai. Coba jalankan lagi.'],
            'scanned_at' => now(),
        ]);
    }
}
