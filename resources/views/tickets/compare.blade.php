@extends('layouts.app')

@section('title', 'Bandingkan scan')

@push('styles')
<style>
    .picker { display: grid; grid-template-columns: 1fr auto 1fr auto; gap: 0.75rem; align-items: end; padding: 1rem 1.25rem; }
    .picker .arrow { padding-bottom: 0.6rem; color: var(--ink-faint); }

    .sides { display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; margin: 1.25rem 0; }
    .side { text-align: center; padding: 1.25rem; }
    .side .when { font-size: 0.8rem; color: var(--ink-soft); }
    .side .grade { font-size: 2.4rem; font-weight: 600; line-height: 1.1; margin-top: 0.35rem; }
    .side .score { font-size: 0.85rem; color: var(--ink-soft); font-variant-numeric: tabular-nums; }

    .delta { display: flex; justify-content: center; gap: 2rem; padding: 0.9rem 1.25rem; flex-wrap: wrap; }
    .delta div { text-align: center; }
    .delta b { display: block; font-size: 1.3rem; font-variant-numeric: tabular-nums; }
    .delta span { font-size: 0.78rem; color: var(--ink-soft); }

    .diff-row { display: grid; grid-template-columns: minmax(0, 1fr) 5rem 5rem 8rem; gap: 0.75rem; align-items: center; padding: 0.7rem 1.25rem; border-bottom: 1px solid var(--line); }
    .diff-row:last-child { border-bottom: 0; }
    .diff-row .state { text-align: center; font-weight: 600; }
    .state-pass { color: var(--ok); }
    .state-fail { color: var(--danger); }
    .state-none { color: var(--ink-faint); }
    .tag { justify-self: end; font-size: 0.72rem; padding: 0.15rem 0.5rem; border-radius: 999px; border: 1px solid var(--line); color: var(--ink-soft); white-space: nowrap; }
    .tag-fixed { color: var(--ok); border-color: color-mix(in srgb, var(--ok) 40%, transparent); }
    .tag-broken { color: var(--danger); border-color: color-mix(in srgb, var(--danger) 40%, transparent); }
    .row-changed { background: var(--surface-2); }
    .head-row { font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--ink-faint); }

    @media (max-width: 640px) {
        .picker { grid-template-columns: 1fr; }
        .picker .arrow { display: none; }
        .sides { grid-template-columns: 1fr; }
        .diff-row { grid-template-columns: minmax(0, 1fr) 3rem 3rem; }
        .diff-row .tag { grid-column: 1 / -1; justify-self: start; }
    }
</style>
@endpush

@section('content')
@php
    $scoreDelta = $after->score - $before->score;
    $tone = fn ($score) => $score >= 80 ? 'var(--ok)' : ($score >= 60 ? 'var(--sev-medium)' : 'var(--sev-high)');
    $state = fn ($passed) => match ($passed) { true => ['✓', 'state-pass'], false => ['✕', 'state-fail'], default => ['—', 'state-none'] };
@endphp

<a href="{{ route('tickets.show', $ticket) }}" class="back">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
    Kembali ke ticket
</a>

<div class="page-head">
    <div>
        <h1>Bandingkan scan</h1>
        <p class="truncate">{{ $ticket->title }}</p>
    </div>
</div>

<div class="card">
    <form class="picker" method="GET" action="{{ route('tickets.compare', $ticket) }}">
        <div>
            <label class="label" for="before">Scan lama</label>
            <select class="input" id="before" name="before">
                @foreach ($scans as $scan)
                    <option value="{{ $scan->id }}" @selected($scan->id === $before->id)>
                        {{ $scan->created_at->translatedFormat('d M Y, H:i') }} · {{ $scan->grade }} {{ $scan->score }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="arrow" aria-hidden="true">→</div>
        <div>
            <label class="label" for="after">Scan baru</label>
            <select class="input" id="after" name="after">
                @foreach ($scans as $scan)
                    <option value="{{ $scan->id }}" @selected($scan->id === $after->id)>
                        {{ $scan->created_at->translatedFormat('d M Y, H:i') }} · {{ $scan->grade }} {{ $scan->score }}
                    </option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn btn-secondary">Bandingkan</button>
    </form>
</div>

<div class="sides">
    <div class="card side">
        <div class="when">{{ $before->created_at->translatedFormat('d M Y, H:i') }}</div>
        <div class="grade" style="color: {{ $tone($before->score) }}">{{ $before->grade }}</div>
        <div class="score">{{ $before->score }}/100 · {{ count($before->findings ?? []) }} temuan</div>
    </div>
    <div class="card side">
        <div class="when">{{ $after->created_at->translatedFormat('d M Y, H:i') }}</div>
        <div class="grade" style="color: {{ $tone($after->score) }}">{{ $after->grade }}</div>
        <div class="score">{{ $after->score }}/100 · {{ count($after->findings ?? []) }} temuan</div>
    </div>
</div>

<div class="card">
    <div class="delta">
        <div>
            <b style="color: {{ $scoreDelta > 0 ? 'var(--ok)' : ($scoreDelta < 0 ? 'var(--danger)' : 'var(--ink)') }}">
                {{ $scoreDelta > 0 ? '+' : '' }}{{ $scoreDelta }}
            </b>
            <span>perubahan skor</span>
        </div>
        <div>
            <b style="color: {{ $summary['fixed'] > 0 ? 'var(--ok)' : 'var(--ink)' }}">{{ $summary['fixed'] }}</b>
            <span>diperbaiki</span>
        </div>
        <div>
            <b style="color: {{ $summary['broken'] > 0 ? 'var(--danger)' : 'var(--ink)' }}">{{ $summary['broken'] }}</b>
            <span>memburuk</span>
        </div>
        <div>
            <b>{{ $summary['unchanged'] }}</b>
            <span>tetap</span>
        </div>
    </div>
</div>

<div class="card" style="margin-top:1.25rem">
    <div class="card-head">
        <h2>Rincian per pemeriksaan</h2>
        <span class="faint" style="font-size:0.8rem">{{ $rows->count() }} pemeriksaan</span>
    </div>

    <div class="diff-row head-row">
        <span>Pemeriksaan</span>
        <span style="text-align:center">Lama</span>
        <span style="text-align:center">Baru</span>
        <span></span>
    </div>

    @foreach ($rows as $row)
        @php
            [$beforeMark, $beforeClass] = $state($row['before']);
            [$afterMark, $afterClass] = $state($row['after']);
            $changed = in_array($row['change'], ['fixed', 'broken', 'added', 'removed'], true);
        @endphp
        <div class="diff-row {{ $changed ? 'row-changed' : '' }}">
            <div>
                <div style="font-size:0.9rem">{{ $row['label'] }}</div>
                @if ($row['change'] === 'broken' && $row['detail'])
                    <div class="muted" style="font-size:0.8rem;margin-top:0.2rem">{{ $row['detail'] }}</div>
                @endif
            </div>
            <span class="state {{ $beforeClass }}" title="scan lama">{{ $beforeMark }}</span>
            <span class="state {{ $afterClass }}" title="scan baru">{{ $afterMark }}</span>
            <span class="tag tag-{{ $row['change'] }}">
                {{ [
                    'fixed' => 'Diperbaiki',
                    'broken' => 'Memburuk',
                    'added' => 'Pemeriksaan baru',
                    'removed' => 'Tidak diperiksa',
                    'still-failing' => 'Masih gagal',
                    'still-passing' => 'Tetap lolos',
                ][$row['change']] }}
            </span>
        </div>
    @endforeach
</div>
@endsection
