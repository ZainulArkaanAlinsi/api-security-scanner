@extends('layouts.app')

@section('title', 'Dashboard')

@push('styles')
<style>
    .stats {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        border: 1px solid var(--line);
        border-radius: 10px;
        background: var(--surface);
        margin-bottom: 1.25rem;
    }
    .stat { padding: 1rem 1.25rem; }
    .stat + .stat { border-left: 1px solid var(--line); }
    .stat-value { font-size: 1.6rem; font-weight: 600; letter-spacing: -0.02em; line-height: 1.2; font-variant-numeric: tabular-nums; }
    .stat-label { margin-top: 0.15rem; font-size: 0.8rem; color: var(--ink-soft); }
    .stat-danger .stat-value { color: var(--danger); }

    .dist { padding: 1rem 1.25rem; margin-bottom: 1.25rem; }
    .dist-bar { display: flex; height: 8px; margin: 0.75rem 0; border-radius: 4px; overflow: hidden; background: var(--surface-2); gap: 2px; }
    .dist-bar span { display: block; }
    .dist-legend { display: flex; flex-wrap: wrap; gap: 0.5rem 1.25rem; font-size: 0.8rem; }
    .dist-legend b { font-weight: 500; color: var(--ink); margin-left: 0.25rem; font-variant-numeric: tabular-nums; }

    .filters { display: flex; flex-wrap: wrap; gap: 0.5rem; padding: 0.9rem 1.25rem; border-bottom: 1px solid var(--line); }
    .filters .input { height: 36px; font-size: 0.85rem; }
    .filters .search { flex: 1 1 220px; }
    .filters select.input { width: auto; min-width: 140px; background-position: calc(100% - 16px) 15px, calc(100% - 11px) 15px; }
    .filters .btn { height: 36px; }

    .t-title { font-weight: 500; max-width: 340px; }
    .t-url { font-size: 0.78rem; color: var(--ink-faint); max-width: 340px; }
    .row-link { color: inherit; text-decoration: none; }
    .grade-pill {
        display: inline-grid;
        place-items: center;
        width: 26px;
        height: 26px;
        font-size: 0.8rem;
        font-weight: 600;
        color: var(--tone);
        border: 1px solid var(--tone);
        border-radius: 7px;
    }
    .auto-tag {
        display: inline-block;
        margin-left: 0.35rem;
        padding: 0 0.35rem;
        font-family: var(--mono);
        font-size: 0.65rem;
        font-weight: 400;
        color: var(--ink-faint);
        border: 1px solid var(--line);
        border-radius: 4px;
        vertical-align: 1px;
    }
    .row-link:hover .t-title { text-decoration: underline; }

    @media (max-width: 760px) {
        .stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .stat:nth-child(3) { border-left: 0; }
        .stat:nth-child(n+3) { border-top: 1px solid var(--line); }
    }

    /* Below this width the table becomes one card per ticket, so nothing
       important hides behind a horizontal scrollbar. */
    @media (max-width: 640px) {
        .table thead { display: none; }
        .table, .table tbody, .table tr, .table td { display: block; width: 100%; }
        .table tr { padding: 0.9rem 1.25rem; border-bottom: 1px solid var(--line); }
        .table td { padding: 0; border: 0; }
        .table td + td { margin-top: 0.5rem; }
        .table td[data-label] { display: flex; justify-content: space-between; gap: 1rem; align-items: center; }
        .table td[data-label]::before {
            content: attr(data-label);
            font-size: 0.75rem;
            color: var(--ink-faint);
        }
        .table td.num { text-align: left; }
        .t-title, .t-url { max-width: none; }
    }
</style>
@endpush

@section('content')
<div class="page-head">
    <div>
        <h1>Dashboard</h1>
        <p>Ringkasan semua API yang kamu pantau.</p>
    </div>
    <div class="row">
        @if ($stats['total'] > 0)
            <a href="{{ route('tickets.export', request()->query()) }}" class="btn btn-secondary">Unduh CSV</a>
        @endif
        <a href="{{ route('tickets.import') }}" class="btn btn-secondary">Import OpenAPI</a>
        @if ($stats['total'] > 0)
            <form action="{{ route('tickets.scan-all') }}" method="POST" data-busy="Mengantre…"
                data-confirm="Jalankan scan untuk semua endpoint sekarang?">
                @csrf
                <button type="submit" class="btn btn-secondary">Scan semua</button>
            </form>
        @endif
        <a href="{{ route('tickets.create') }}" class="btn btn-primary">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
            Scan baru
        </a>
    </div>
</div>

<div class="stats">
    <div class="stat">
        <div class="stat-value">{{ $stats['total'] }}</div>
        <div class="stat-label">Total ticket</div>
    </div>
    <div class="stat">
        @if ($stats['score'] !== null)
            @php $avg = (int) round($stats['score']); @endphp
            <div class="stat-value" style="color: {{ $avg >= 80 ? 'var(--ok)' : ($avg >= 60 ? 'var(--sev-medium)' : 'var(--sev-high)') }}">
                {{ $avg }}<span style="font-size:0.9rem;color:var(--ink-faint)">/100</span>
            </div>
            <div class="stat-label">Rata-rata skor · grade {{ \App\Services\SecurityScore::grade($avg) }}</div>
        @else
            <div class="stat-value faint">—</div>
            <div class="stat-label">Rata-rata skor</div>
        @endif
    </div>
    <div class="stat">
        <div class="stat-value">{{ $stats['pending'] }}</div>
        <div class="stat-label">Belum di-scan</div>
    </div>
    <div class="stat {{ $stats['risky'] > 0 ? 'stat-danger' : '' }}">
        <div class="stat-value">{{ $stats['risky'] }}</div>
        <div class="stat-label">Risiko tinggi</div>
    </div>
</div>

@php
    $severityLevels = ['critical' => 'Critical', 'high' => 'High', 'medium' => 'Medium', 'low' => 'Low'];
    $severityTotal = $severityCounts->sum();
@endphp

@if ($severityTotal > 0)
<div class="card dist">
    <div class="row" style="justify-content:space-between">
        <h2>Distribusi tingkat risiko</h2>
        <span class="faint" style="font-size:0.8rem">{{ $severityTotal }} ticket dengan temuan</span>
    </div>
    <div class="dist-bar" role="img" aria-label="Distribusi tingkat risiko">
        @foreach ($severityLevels as $key => $label)
            @if (($severityCounts[$key] ?? 0) > 0)
                <span style="width: {{ $severityCounts[$key] / $severityTotal * 100 }}%; background: var(--sev-{{ $key }})"></span>
            @endif
        @endforeach
    </div>
    <div class="dist-legend">
        @foreach ($severityLevels as $key => $label)
            <span class="sev sev-{{ $key }}">{{ $label }}<b>{{ $severityCounts[$key] ?? 0 }}</b></span>
        @endforeach
    </div>
</div>
@endif

<div class="card">
    <form class="filters" method="GET" action="{{ route('tickets.index') }}" role="search">
        <label class="sr-only" for="q">Cari ticket</label>
        <input class="input search" type="search" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Cari judul atau URL">

        <label class="sr-only" for="status">Status</label>
        <select class="input" id="status" name="status">
            <option value="">Semua status</option>
            @foreach (['pending' => 'Belum di-scan', 'scanning' => 'Sedang scan', 'completed' => 'Selesai', 'failed' => 'Gagal'] as $value => $label)
                <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
            @endforeach
        </select>

        <label class="sr-only" for="severity">Tingkat risiko</label>
        <select class="input" id="severity" name="severity">
            <option value="">Semua risiko</option>
            @foreach ($severityLevels as $value => $label)
                <option value="{{ $value }}" @selected(($filters['severity'] ?? '') === $value)>{{ $label }}</option>
            @endforeach
        </select>

        <button type="submit" class="btn btn-secondary">Terapkan</button>
        @if (array_filter($filters))
            <a href="{{ route('tickets.index') }}" class="btn btn-ghost">Reset</a>
        @endif
    </form>

    @if ($tickets->isEmpty())
        <div class="empty">
            @if (array_filter($filters))
                <h2>Tidak ada hasil</h2>
                <p>Tidak ada ticket yang cocok dengan filter ini. Coba kata kunci atau filter lain.</p>
                <a href="{{ route('tickets.index') }}" class="btn btn-secondary">Hapus filter</a>
            @else
                <h2>Belum ada ticket</h2>
                <p>Tambahkan URL API yang ingin kamu periksa. Scan pertama hanya butuh beberapa detik.</p>
                <a href="{{ route('tickets.create') }}" class="btn btn-primary">Buat scan pertama</a>
            @endif
        </div>
    @else
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Ticket</th>
                        <th>Status</th>
                        <th>Skor</th>
                        <th>Risiko</th>
                        <th>Scan terakhir</th>
                        <th class="num"><span class="sr-only">Aksi</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($tickets as $ticket)
                        <tr>
                            <td>
                                <a class="row-link" href="{{ route('tickets.show', $ticket) }}">
                                    <div class="t-title truncate">
                                        {{ $ticket->title }}
                                        @if ($ticket->auto_scan)
                                            <span class="auto-tag" title="Monitoring otomatis aktif">auto</span>
                                        @endif
                                    </div>
                                    <div class="t-url mono truncate">{{ $ticket->api_url }}</div>
                                </a>
                            </td>
                            <td data-label="Status">@include('tickets.partials.status', ['status' => $ticket->status])</td>
                            <td data-label="Skor">
                                @if ($ticket->score !== null)
                                    <span class="grade-pill" style="--tone: {{ $ticket->score >= 80 ? 'var(--ok)' : ($ticket->score >= 60 ? 'var(--sev-medium)' : 'var(--sev-high)') }}"
                                        title="Skor {{ $ticket->score }}/100">{{ $ticket->grade }}</span>
                                @else
                                    <span class="faint">—</span>
                                @endif
                            </td>
                            <td data-label="Risiko">
                                @if ($ticket->severity)
                                    <span class="sev sev-{{ $ticket->severity }}">{{ ucfirst($ticket->severity) }}</span>
                                @else
                                    <span class="faint">—</span>
                                @endif
                            </td>
                            <td class="muted" data-label="Scan terakhir" style="white-space:nowrap">
                                {{ $ticket->scanned_at ? $ticket->scanned_at->diffForHumans() : 'Belum pernah' }}
                            </td>
                            <td class="num">
                                <a href="{{ route('tickets.show', $ticket) }}" class="btn btn-ghost btn-sm">Buka</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $tickets->links('partials.pagination') }}
    @endif
</div>
@endsection
