@extends('layouts.app')

@section('title', 'Statistik')

@push('styles')
<style>
    .stat-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); border: 1px solid var(--line); border-radius: 10px; background: var(--surface); margin-bottom: 1.25rem; }
    .stat-grid > div { padding: 1rem 1.25rem; }
    .stat-grid > div + div { border-left: 1px solid var(--line); }
    .stat-grid b { display: block; font-size: 1.6rem; font-weight: 600; letter-spacing: -0.02em; font-variant-numeric: tabular-nums; }
    .stat-grid span { font-size: 0.8rem; color: var(--ink-soft); }

    .panels { display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; }

    .bars { padding: 1rem 1.25rem; }
    .bar-row { display: grid; grid-template-columns: 9.5rem 1fr 2rem; gap: 0.75rem; align-items: center; padding: 0.3rem 0; font-size: 0.85rem; }
    .bar-track { height: 8px; background: var(--surface-2); border-radius: 4px; overflow: hidden; }
    .bar-fill { height: 100%; border-radius: 4px; background: var(--tone, var(--accent)); }
    .bar-row .count { text-align: right; font-variant-numeric: tabular-nums; color: var(--ink-soft); }

    .grades { display: grid; grid-template-columns: repeat(6, 1fr); gap: 0.5rem; padding: 1rem 1.25rem; }
    .grade-col { text-align: center; }
    .grade-bar { height: 70px; display: flex; align-items: flex-end; }
    .grade-bar span { display: block; width: 100%; border-radius: 3px 3px 0 0; min-height: 3px; }
    .grade-label { margin-top: 0.4rem; font-weight: 600; font-size: 0.9rem; }
    .grade-count { font-size: 0.75rem; color: var(--ink-soft); font-variant-numeric: tabular-nums; }

    .chart { padding: 1rem 1.25rem; }
    .chart svg { width: 100%; height: 160px; overflow: visible; }

    .list-row { display: flex; justify-content: space-between; gap: 1rem; align-items: center; padding: 0.7rem 1.25rem; border-bottom: 1px solid var(--line); font-size: 0.875rem; }
    .list-row:last-child { border-bottom: 0; }

    @media (max-width: 800px) {
        .panels { grid-template-columns: 1fr; }
        .stat-grid { grid-template-columns: 1fr; }
        .stat-grid > div + div { border-left: 0; border-top: 1px solid var(--line); }
        .bar-row { grid-template-columns: 7rem 1fr 2rem; }
    }
</style>
@endpush

@section('content')
@php
    $tone = fn ($score) => $score >= 80 ? 'var(--ok)' : ($score >= 60 ? 'var(--sev-medium)' : 'var(--sev-high)');
    $gradeTone = ['A' => 'var(--ok)', 'B' => 'var(--ok)', 'C' => 'var(--sev-medium)', 'D' => 'var(--sev-medium)', 'E' => 'var(--sev-high)', 'F' => 'var(--sev-high)'];
    $maxGrade = max(1, $grades->max());
    $maxCategory = max(1, $categories->max() ?: 1);
    $totalFindings = $severities->sum();
@endphp

<div class="page-head">
    <div>
        <h1>Statistik</h1>
        <p>Gambaran keamanan seluruh endpoint yang kamu pantau.</p>
    </div>
    <a href="{{ route('tickets.index') }}" class="btn btn-secondary">Ke dashboard</a>
</div>

@if ($scanned === 0)
    <div class="card empty">
        <h2>Belum ada data</h2>
        <p>Statistik muncul setelah ada endpoint yang selesai di-scan.</p>
        <a href="{{ route('tickets.create') }}" class="btn btn-primary">Buat scan pertama</a>
    </div>
@else
    <div class="stat-grid">
        <div>
            <b style="color: {{ $tone($averageScore) }}">{{ $averageScore }}<span style="font-size:0.9rem;color:var(--ink-faint)">/100</span></b>
            <span>Rata-rata skor · grade {{ \App\Services\SecurityScore::grade($averageScore) }}</span>
        </div>
        <div>
            <b>{{ $scanned }}<span style="font-size:0.9rem;color:var(--ink-faint)">/{{ $total }}</span></b>
            <span>Endpoint sudah di-scan</span>
        </div>
        <div>
            <b style="color: {{ $totalFindings > 0 ? 'var(--sev-high)' : 'var(--ok)' }}">{{ $totalFindings }}</b>
            <span>Total temuan aktif</span>
        </div>
    </div>

    <div class="panels">
        <div class="card">
            <div class="card-head"><h2>Sebaran grade</h2></div>
            <div class="grades">
                @foreach ($grades as $grade => $count)
                    <div class="grade-col">
                        <div class="grade-bar" title="{{ $count }} endpoint">
                            <span style="height: {{ $count === 0 ? 3 : max(6, $count / $maxGrade * 100) }}%; background: {{ $count === 0 ? 'var(--line)' : $gradeTone[$grade] }}"></span>
                        </div>
                        <div class="grade-label" style="color: {{ $count > 0 ? $gradeTone[$grade] : 'var(--ink-faint)' }}">{{ $grade }}</div>
                        <div class="grade-count">{{ $count }}</div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="card">
            <div class="card-head"><h2>Temuan per kategori</h2></div>
            <div class="bars">
                @forelse ($categories as $label => $count)
                    <div class="bar-row">
                        <span class="truncate">{{ $label }}</span>
                        <span class="bar-track"><span class="bar-fill" style="width: {{ $count / $maxCategory * 100 }}%; --tone: var(--sev-medium)"></span></span>
                        <span class="count">{{ $count }}</span>
                    </div>
                @empty
                    <p class="muted" style="font-size:0.875rem">Tidak ada temuan sama sekali. Bagus.</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="card" style="margin-top:1.25rem">
        <div class="card-head">
            <h2>Tren skor 14 hari terakhir</h2>
            <span class="faint" style="font-size:0.8rem">{{ $trend->sum('scans') }} scan</span>
        </div>
        <div class="chart">
            @if ($trend->count() < 2)
                <p class="muted" style="font-size:0.875rem">Butuh scan di minimal dua hari berbeda untuk menggambar tren.</p>
            @else
                @php
                    $points = $trend->values();
                    $step = 100 / ($points->count() - 1);
                    $coords = $points->map(fn ($p, $i) => round($i * $step, 2).','.round(100 - $p['average'], 2))->implode(' ');
                @endphp
                <svg viewBox="0 0 100 100" preserveAspectRatio="none" role="img"
                    aria-label="Tren rata-rata skor dari {{ $points->first()['average'] }} menjadi {{ $points->last()['average'] }}">
                    <line x1="0" y1="20" x2="100" y2="20" stroke="var(--line)" stroke-width="0.4" stroke-dasharray="2 2"/>
                    <line x1="0" y1="50" x2="100" y2="50" stroke="var(--line)" stroke-width="0.4" stroke-dasharray="2 2"/>
                    <polyline points="{{ $coords }}" fill="none" stroke="{{ $tone($points->last()['average']) }}"
                        stroke-width="1.2" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke"/>
                    @foreach ($points as $i => $point)
                        <circle cx="{{ round($i * $step, 2) }}" cy="{{ round(100 - $point['average'], 2) }}" r="1"
                            fill="{{ $tone($point['average']) }}" vector-effect="non-scaling-stroke">
                            <title>{{ $point['day'] }}: {{ $point['average'] }}/100</title>
                        </circle>
                    @endforeach
                </svg>
                <div class="row" style="justify-content:space-between;margin-top:0.5rem">
                    <span class="faint mono" style="font-size:0.72rem">{{ $points->first()['day'] }} · {{ $points->first()['average'] }}</span>
                    <span class="faint mono" style="font-size:0.72rem">{{ $points->last()['day'] }} · {{ $points->last()['average'] }}</span>
                </div>
            @endif
        </div>
    </div>

    <div class="panels" style="margin-top:1.25rem">
        <div class="card">
            <div class="card-head"><h2>Masalah paling sering</h2></div>
            @forelse ($common as $title => $data)
                <div class="list-row">
                    <span class="truncate">{{ $title }}</span>
                    <span class="row" style="gap:0.75rem">
                        <span class="sev sev-{{ $data['severity'] }}">{{ ucfirst($data['severity']) }}</span>
                        <span class="faint">{{ $data['count'] }}×</span>
                    </span>
                </div>
            @empty
                <p class="muted" style="padding:1rem 1.25rem;font-size:0.875rem">Belum ada temuan.</p>
            @endforelse
        </div>

        <div class="card">
            <div class="card-head"><h2>Endpoint paling rawan</h2></div>
            @foreach ($worst as $ticket)
                <div class="list-row">
                    <a class="truncate" href="{{ route('tickets.show', $ticket) }}" style="color:inherit">{{ $ticket->title }}</a>
                    <span class="row" style="gap:0.75rem">
                        <span style="color: {{ $tone($ticket->score) }};font-weight:600">{{ $ticket->grade }}</span>
                        <span class="faint mono" style="font-size:0.78rem">{{ $ticket->score }}/100</span>
                    </span>
                </div>
            @endforeach
        </div>
    </div>
@endif
@endsection
