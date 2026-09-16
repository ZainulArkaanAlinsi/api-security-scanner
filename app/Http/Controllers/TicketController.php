<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TicketController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'q' => 'nullable|string|max:100',
            'status' => 'nullable|in:pending,scanning,completed,failed',
            'severity' => 'nullable|in:low,medium,high,critical',
        ]);

        $user = $request->user();

        $tickets = $this->filtered($request, $filters)
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $statusCounts = $user->tickets()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $severityCounts = $user->tickets()
            ->whereNotNull('severity')
            ->selectRaw('severity, count(*) as total')
            ->groupBy('severity')
            ->pluck('total', 'severity');

        $stats = [
            'total' => (int) $statusCounts->sum(),
            'completed' => (int) ($statusCounts['completed'] ?? 0),
            'pending' => (int) ($statusCounts['pending'] ?? 0),
            'failed' => (int) ($statusCounts['failed'] ?? 0),
            'risky' => (int) (($severityCounts['high'] ?? 0) + ($severityCounts['critical'] ?? 0)),
        ];

        return view('tickets.index', compact('tickets', 'stats', 'severityCounts', 'filters'));
    }

    /**
     * Download the current dashboard selection as CSV, filters included.
     */
    public function export(Request $request)
    {
        $filters = $request->validate([
            'q' => 'nullable|string|max:100',
            'status' => 'nullable|in:pending,scanning,completed,failed',
            'severity' => 'nullable|in:low,medium,high,critical',
        ]);

        $tickets = $this->filtered($request, $filters)->latest();
        $filename = 'api-scanner-tickets-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($tickets) {
            $handle = fopen('php://output', 'w');

            // BOM so Excel opens UTF-8 correctly.
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['id', 'judul', 'api_url', 'status', 'risiko', 'jumlah_temuan', 'monitoring', 'scan_terakhir', 'dibuat']);

            $tickets->chunk(200, function ($chunk) use ($handle) {
                foreach ($chunk as $ticket) {
                    fputcsv($handle, [
                        $ticket->id,
                        $ticket->title,
                        $ticket->api_url,
                        $ticket->status,
                        $ticket->severity ?? '',
                        count($ticket->findings ?? []),
                        $ticket->auto_scan ? 'aktif' : 'mati',
                        $ticket->scanned_at?->toDateTimeString() ?? '',
                        $ticket->created_at->toDateTimeString(),
                    ]);
                }
            });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function create()
    {
        return view('tickets.create');
    }

    public function store(Request $request)
    {
        $ticket = $request->user()->tickets()->create($this->validated($request));

        return redirect()->route('tickets.show', $ticket)->with('success', 'Ticket dibuat. Jalankan scan untuk mulai mengecek API.');
    }

    public function show(Ticket $ticket)
    {
        Gate::authorize('view', $ticket);

        $history = $ticket->scans()->orderByDesc('id')->limit(10)->get();

        // Compare the two most recent successful scans: what got fixed, what is new.
        [$latest, $previous] = $history->where('status', 'completed')->values()->take(2)->pad(2, null)->all();

        $changes = null;

        if ($latest && $previous) {
            $changes = [
                'fixed' => array_values(array_diff($previous->findingTitles(), $latest->findingTitles())),
                'new' => array_values(array_diff($latest->findingTitles(), $previous->findingTitles())),
                'since' => $previous->created_at,
            ];
        }

        return view('tickets.show', compact('ticket', 'history', 'changes'));
    }

    public function edit(Ticket $ticket)
    {
        Gate::authorize('update', $ticket);

        return view('tickets.edit', compact('ticket'));
    }

    public function update(Request $request, Ticket $ticket)
    {
        Gate::authorize('update', $ticket);

        $ticket->fill($this->validated($request));

        // Old results describe a different endpoint once the URL changes.
        if ($ticket->isDirty('api_url')) {
            $ticket->fill([
                'status' => 'pending',
                'severity' => null,
                'findings' => null,
                'scan_result' => null,
                'scanned_at' => null,
            ]);

            $ticket->scans()->delete();
        }

        $ticket->save();

        return redirect()->route('tickets.show', $ticket)->with('success', 'Ticket diperbarui.');
    }

    public function destroy(Ticket $ticket)
    {
        Gate::authorize('delete', $ticket);

        $ticket->delete();

        return redirect()->route('tickets.index')->with('success', 'Ticket dihapus.');
    }

    /**
     * The signed-in user's tickets, narrowed by the dashboard filters.
     */
    private function filtered(Request $request, array $filters)
    {
        return $request->user()->tickets()
            ->when($filters['q'] ?? null, function ($query, string $search) {
                $query->where(fn ($q) => $q
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('api_url', 'like', "%{$search}%"));
            })
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['severity'] ?? null, fn ($query, string $severity) => $query->where('severity', $severity));
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'api_url' => 'required|url:http,https|max:2048',
        ], [
            'api_url.url' => 'URL harus valid dan diawali http:// atau https://.',
        ]);
    }
}
