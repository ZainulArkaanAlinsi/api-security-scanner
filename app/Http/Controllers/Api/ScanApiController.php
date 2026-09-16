<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ScanTicketJob;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Minimal API so a CI pipeline can scan an endpoint and read the result.
 */
class ScanApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tickets = $request->user()->tickets()->latest()->limit(100)->get();

        return response()->json([
            'data' => $tickets->map(fn (Ticket $ticket) => $this->summary($ticket)),
        ]);
    }

    /**
     * Queue a scan. An endpoint already on the account is reused rather than
     * duplicated, so calling this on every CI run keeps one history per URL.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'url' => 'required|url:http,https|max:2048',
            'title' => 'nullable|string|max:255',
        ]);

        $user = $request->user();
        $ticket = $user->tickets()->firstWhere('api_url', $data['url']);

        if (! $ticket) {
            $ticket = $user->tickets()->create([
                'title' => $data['title'] ?? 'API '.parse_url($data['url'], PHP_URL_HOST),
                'api_url' => $data['url'],
            ]);
        }

        if ($ticket->status === 'scanning') {
            return response()->json([
                'message' => 'Scan untuk endpoint ini sedang berjalan.',
                'data' => $this->summary($ticket),
            ], 409);
        }

        $ticket->update(['status' => 'scanning']);
        ScanTicketJob::dispatch($ticket);

        return response()->json([
            'message' => 'Scan masuk antrean.',
            'data' => $this->summary($ticket),
        ], 202);
    }

    public function show(Request $request, Ticket $ticket): JsonResponse
    {
        if ($ticket->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Ticket tidak ditemukan.'], 404);
        }

        return response()->json([
            'data' => $this->summary($ticket) + [
                'findings' => $ticket->findings ?? [],
                'result' => $ticket->scan_result,
            ],
        ]);
    }

    private function summary(Ticket $ticket): array
    {
        return [
            'id' => $ticket->id,
            'title' => $ticket->title,
            'url' => $ticket->api_url,
            'status' => $ticket->status,
            'score' => $ticket->score,
            'grade' => $ticket->grade,
            'severity' => $ticket->severity,
            'findings_count' => count($ticket->findings ?? []),
            'scanned_at' => $ticket->scanned_at?->toIso8601String(),
        ];
    }
}
