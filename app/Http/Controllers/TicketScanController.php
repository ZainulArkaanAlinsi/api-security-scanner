<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Services\ScanRunner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TicketScanController extends Controller
{
    public function scan(Ticket $ticket, ScanRunner $runner)
    {
        Gate::authorize('update', $ticket);

        $scan = $runner->run($ticket);

        if ($scan->status === 'failed') {
            return redirect()->route('tickets.show', $ticket)->with('error', $scan->error);
        }

        $count = count($scan->findings ?? []);

        return redirect()->route('tickets.show', $ticket)->with('success', $count === 0
            ? 'Scan selesai. Tidak ada temuan.'
            : "Scan selesai dengan {$count} temuan.");
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
