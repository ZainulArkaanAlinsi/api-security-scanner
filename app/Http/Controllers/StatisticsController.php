<?php

namespace App\Http\Controllers;

use App\Models\Scan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StatisticsController extends Controller
{
    private const CATEGORY_LABELS = [
        'transport' => 'Transport & TLS',
        'header' => 'Header keamanan',
        'cookie' => 'Cookie',
        'exposure' => 'Berkas terekspos',
        'data-exposure' => 'Data sensitif',
        'auth' => 'Autentikasi',
        'config' => 'Konfigurasi',
        'error-handling' => 'Penanganan error',
        'performance' => 'Performa',
    ];

    public function index(Request $request)
    {
        $user = $request->user();
        $tickets = $user->tickets()->where('status', 'completed')->get();
        $findings = $tickets->flatMap(fn ($ticket) => $ticket->findings ?? []);

        return view('statistics', [
            'scanned' => $tickets->count(),
            'total' => $user->tickets()->count(),
            'averageScore' => $tickets->isEmpty() ? null : (int) round($tickets->avg('score')),
            'grades' => collect(['A', 'B', 'C', 'D', 'E', 'F'])
                ->mapWithKeys(fn ($grade) => [$grade => $tickets->where('grade', $grade)->count()]),
            'severities' => collect(['critical', 'high', 'medium', 'low'])
                ->mapWithKeys(fn ($level) => [$level => $findings->where('severity', $level)->count()]),
            'categories' => $findings
                ->groupBy(fn ($finding) => $finding['category'] ?? 'lainnya')
                ->map->count()
                ->sortDesc()
                ->mapWithKeys(fn ($count, $key) => [self::CATEGORY_LABELS[$key] ?? ucfirst($key) => $count]),
            'common' => $findings
                ->groupBy('title')
                ->map(fn ($group) => ['count' => $group->count(), 'severity' => $group->first()['severity']])
                ->sortByDesc('count')
                ->take(5),
            'trend' => $this->trend($user->id),
            'worst' => $tickets->whereNotNull('score')->sortBy('score')->take(5),
        ]);
    }

    /**
     * Average score per day over the last two weeks, across every endpoint.
     */
    private function trend(int $userId)
    {
        return Scan::query()
            ->join('tickets', 'tickets.id', '=', 'scans.ticket_id')
            ->where('tickets.user_id', $userId)
            ->where('scans.status', 'completed')
            ->whereNotNull('scans.score')
            ->where('scans.created_at', '>=', now()->subDays(14)->startOfDay())
            ->groupBy('day')
            ->orderBy('day')
            ->get([
                DB::raw('DATE(scans.created_at) as day'),
                DB::raw('AVG(scans.score) as average'),
                DB::raw('COUNT(*) as scans'),
            ])
            ->map(fn ($row) => [
                'day' => $row->day,
                'average' => (int) round($row->average),
                'scans' => (int) $row->scans,
            ]);
    }
}
