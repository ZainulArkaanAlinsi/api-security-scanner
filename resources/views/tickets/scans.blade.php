@extends('layouts.app')

@section('title', 'Riwayat scan')

@push('styles')
<style>
    .scan-row { display: grid; grid-template-columns: 11rem minmax(0, 1fr) auto; gap: 1rem; align-items: center; padding: 0.85rem 1.25rem; border-bottom: 1px solid var(--line); }
    .scan-row:last-child { border-bottom: 0; }
    .scan-when { font-size: 0.875rem; }
    .scan-note { font-size: 0.8rem; color: var(--ink-soft); }
    @media (max-width: 640px) {
        .scan-row { grid-template-columns: 1fr; gap: 0.35rem; }
    }
</style>
@endpush

@section('content')
<div style="max-width:760px">
    <a href="{{ route('tickets.show', $ticket) }}" class="back">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
        Kembali ke ticket
    </a>

    <div class="page-head">
        <div>
            <h1>Riwayat scan</h1>
            <p class="truncate">{{ $ticket->title }}</p>
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <h2>Semua scan</h2>
            <span class="faint" style="font-size:0.8rem">{{ $scans->total() }} total</span>
        </div>

        @forelse ($scans as $scan)
            <div class="scan-row">
                <span class="scan-when">{{ $scan->created_at->translatedFormat('d M Y, H:i') }}</span>
                <span class="scan-note">
                    @if ($scan->status === 'failed')
                        {{ $scan->error }}
                    @else
                        {{ count($scan->findings ?? []) }} temuan
                    @endif
                </span>
                <span class="row" style="gap:0.75rem">
                    @if ($scan->status === 'failed')
                        <span class="badge badge-failed">Gagal</span>
                    @elseif ($scan->severity)
                        <span class="sev sev-{{ $scan->severity }}">{{ ucfirst($scan->severity) }}</span>
                    @else
                        <span class="badge badge-completed">Bersih</span>
                    @endif
                </span>
            </div>
        @empty
            <div class="empty">
                <h2>Belum ada riwayat</h2>
                <p>Ticket ini belum pernah di-scan.</p>
            </div>
        @endforelse

        {{ $scans->links('partials.pagination') }}
    </div>
</div>
@endsection
