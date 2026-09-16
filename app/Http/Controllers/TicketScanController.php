<?php

namespace App\Http\Controllers;

use App\Jobs\ScanTicketJob;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TicketScanController extends Controller
{
    public function scan(Ticket $ticket)
    {
        Gate::authorize('update', $ticket);

        if ($ticket->status === 'scanning') {
            return redirect()->route('tickets.show', $ticket)
                ->with('error', 'Ticket ini sedang di-scan. Tunggu sampai scan yang berjalan selesai.');
        }

        // Queued, so a slow endpoint never holds the browser hostage.
        $ticket->update(['status' => 'scanning']);

        ScanTicketJob::dispatch($ticket);

        return redirect()->route('tickets.show', $ticket)
            ->with('success', 'Scan dimulai. Halaman ini akan memperbarui dirinya sendiri saat hasilnya siap.');
    }

    /**
     * Queue a scan for every endpoint that is not already running.
     */
    public function scanAll(Request $request)
    {
        $tickets = $request->user()->tickets()
            ->where('status', '!=', 'scanning')
            ->limit(50)
            ->get();

        foreach ($tickets as $ticket) {
            $ticket->update(['status' => 'scanning']);
            ScanTicketJob::dispatch($ticket);
        }

        return redirect()->route('tickets.index')->with('success', $tickets->isEmpty()
            ? 'Tidak ada endpoint yang perlu di-scan.'
            : "{$tickets->count()} endpoint masuk antrean. Hasilnya muncul satu per satu.");
    }

    public function monitoring(Request $request, Ticket $ticket)
    {
        Gate::authorize('update', $ticket);

        $enabled = $request->boolean('auto_scan');

        $ticket->update(['auto_scan' => $enabled]);

        return redirect()->route('tickets.show', $ticket)->with('success', $enabled
            ? 'Monitoring otomatis aktif. Ticket ini akan di-scan ulang setiap hari.'
            : 'Monitoring otomatis dimatikan.');
    }

    public function history(Ticket $ticket)
    {
        Gate::authorize('view', $ticket);

        return view('tickets.scans', [
            'ticket' => $ticket,
            'scans' => $ticket->scans()->orderByDesc('id')->paginate(25),
        ]);
    }

    public function report(Ticket $ticket)
    {
        Gate::authorize('view', $ticket);

        if (! $ticket->scanned_at) {
            return redirect()->route('tickets.show', $ticket)->with('error', 'Jalankan scan dulu sebelum mengunduh laporan.');
        }

        $report = [
            'ticket' => [
                'id' => $ticket->id,
                'title' => $ticket->title,
                'api_url' => $ticket->api_url,
                'description' => $ticket->description,
            ],
            'status' => $ticket->status,
            'severity' => $ticket->severity,
            'scanned_at' => $ticket->scanned_at->toIso8601String(),
            'findings' => $ticket->findings ?? [],
            'result' => $ticket->scan_result,
            'history' => $ticket->scans()->orderByDesc('id')->limit(20)->get()
                ->map(fn ($scan) => [
                    'scanned_at' => $scan->created_at->toIso8601String(),
                    'status' => $scan->status,
                    'severity' => $scan->severity,
                    'findings' => count($scan->findings ?? []),
                ]),
            'generated_at' => now()->toIso8601String(),
        ];

        return response()->streamDownload(
            fn () => print json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            "scan-report-{$ticket->id}.json",
            ['Content-Type' => 'application/json']
        );
    }

    public function print(Ticket $ticket)
    {
        Gate::authorize('view', $ticket);

        if ($ticket->status !== 'completed') {
            return redirect()->route('tickets.show', $ticket)->with('error', 'Laporan cetak tersedia setelah scan berhasil.');
        }

        return view('tickets.print', compact('ticket'));
    }
}
