@extends('layouts.app')

@section('title', $ticket->title)

@push('styles')
<style>
    .detail-grid { display: grid; grid-template-columns: minmax(0, 1fr) 300px; gap: 1.25rem; align-items: start; }

    .summary {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        border-bottom: 1px solid var(--line);
    }
    .summary > div { padding: 0.9rem 1.25rem; }
    .summary > div + div { border-left: 1px solid var(--line); }
    .summary dt { font-size: 0.75rem; color: var(--ink-faint); }
    .summary dd { margin-top: 0.2rem; font-weight: 500; font-variant-numeric: tabular-nums; }

    .checks { list-style: none; }
    .check-row {
        display: grid;
        grid-template-columns: 22px minmax(0, 1fr) auto;
        gap: 0.75rem;
        padding: 0.8rem 1.25rem;
        border-bottom: 1px solid var(--line);
    }
    .check-row:last-child { border-bottom: 0; }
    .mark {
        display: grid;
        place-items: center;
        width: 20px;
        height: 20px;
        margin-top: 1px;
        border-radius: 50%;
        font-size: 0.7rem;
        font-weight: 700;
    }
    .mark-pass { color: var(--ok); background: var(--ok-bg); }
    .mark-fail { color: var(--danger); background: var(--danger-bg); }
    .check-label { font-size: 0.9rem; }
    .check-detail { margin-top: 0.25rem; font-size: 0.825rem; color: var(--ink-soft); }

    .meta { list-style: none; font-size: 0.85rem; }
    .meta li { display: flex; justify-content: space-between; gap: 1rem; padding: 0.55rem 0; border-bottom: 1px solid var(--line); }
    .meta li:last-child { border-bottom: 0; }
    .meta span:first-child { color: var(--ink-soft); }

    .headers { font-family: var(--mono); font-size: 0.75rem; }
    .headers div { display: grid; grid-template-columns: 11rem minmax(0, 1fr); gap: 0.75rem; padding: 0.45rem 1.25rem; border-bottom: 1px solid var(--line); }
    .headers div:last-child { border-bottom: 0; }
    .headers dt { color: var(--ink-faint); overflow-wrap: anywhere; }
    .headers dd { overflow-wrap: anywhere; }

    .danger-zone { border-color: color-mix(in srgb, var(--danger) 30%, var(--line)); }

    .changes { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .changes > div { padding: 0.9rem 1.25rem; }
    .changes > div + div { border-left: 1px solid var(--line); }
    .changes h3 { font-size: 0.8rem; font-weight: 500; margin-bottom: 0.4rem; }
    .changes ul { list-style: none; font-size: 0.85rem; }
    .changes li { padding: 0.15rem 0; }
    .changes .fixed h3 { color: var(--ok); }
    .changes .new h3 { color: var(--danger); }

    .trend { display: flex; align-items: flex-end; gap: 6px; height: 56px; padding: 1rem 1.25rem 0; overflow-x: auto; }
    .trend-col { flex: 1 0 8px; min-width: 8px; max-width: 28px; height: 100%; display: flex; align-items: flex-end; }
    .trend-col span { display: block; width: 100%; border-radius: 2px; }

    .spinner {
        width: 18px;
        height: 18px;
        flex: none;
        border: 2px solid var(--line-strong);
        border-top-color: var(--accent);
        border-radius: 50%;
        animation: spin 0.8s linear infinite;
    }
    @keyframes spin { to { transform: rotate(360deg); } }
    @media (prefers-reduced-motion: reduce) { .spinner { animation-duration: 3s; } }

    .score-card { display: flex; align-items: center; gap: 1.5rem; }
    .score-ring {
        flex: none;
        width: 118px;
        height: 118px;
        border-radius: 50%;
        display: grid;
        place-items: center;
        background: conic-gradient(var(--tone) calc(var(--value) * 1%), var(--surface-2) 0);
    }
    .score-inner {
        width: 92px;
        height: 92px;
        border-radius: 50%;
        background: var(--surface);
        display: grid;
        place-items: center;
        line-height: 1.1;
    }
    .score-grade { font-size: 2rem; font-weight: 600; letter-spacing: -0.02em; }
    .score-value { font-size: 0.8rem; color: var(--ink-soft); font-variant-numeric: tabular-nums; }
    .score-max { color: var(--ink-faint); }
    @media (max-width: 520px) {
        .score-card { flex-direction: column; align-items: flex-start; gap: 1rem; }
    }

    .history { list-style: none; font-size: 0.85rem; }
    .history li { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 0.75rem; align-items: center; padding: 0.6rem 1.25rem; border-bottom: 1px solid var(--line); }
    .history li:last-child { border-bottom: 0; }
    .history .when { color: var(--ink-soft); }
    .history .latest { font-size: 0.7rem; color: var(--ink-faint); margin-left: 0.4rem; }

    @media (max-width: 600px) {
        .changes { grid-template-columns: 1fr; }
        .changes > div + div { border-left: 0; border-top: 1px solid var(--line); }
    }

    @media (max-width: 900px) {
        .detail-grid { grid-template-columns: 1fr; }
    }
    @media (max-width: 600px) {
        .summary { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .summary > div:nth-child(3) { border-left: 0; }
        .summary > div:nth-child(n+3) { border-top: 1px solid var(--line); }
        .headers div { grid-template-columns: 1fr; gap: 0.1rem; }
    }
</style>
@endpush

@if ($ticket->status === 'scanning')
    @push('scripts')
        <script>
            // Poll while the queued scan runs; stops as soon as the status changes.
            setTimeout(() => window.location.reload(), 5000);
        </script>
    @endpush
@endif

@section('content')
@php
    $result = $ticket->scan_result ?? [];
    $checks = $result['checks'] ?? [];
    $passed = collect($checks)->where('passed', true)->count();
@endphp

<a href="{{ route('tickets.index') }}" class="back">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
    Dashboard
</a>

<div class="page-head">
    <div style="min-width:0">
        <div class="row" style="margin-bottom:0.4rem">
            @include('tickets.partials.status', ['status' => $ticket->status])
            @if ($ticket->severity)
                <span class="sev sev-{{ $ticket->severity }}">{{ ucfirst($ticket->severity) }}</span>
            @endif
            @if ($ticket->grade)
                <span class="badge" title="Skor keamanan {{ $ticket->score }}/100">Grade {{ $ticket->grade }}</span>
            @endif
        </div>
        <h1 style="overflow-wrap:anywhere">{{ $ticket->title }}</h1>
        <p class="mono" style="overflow-wrap:anywhere">{{ $ticket->api_url }}</p>
    </div>
    <div class="row">
        <a href="{{ route('tickets.edit', $ticket) }}" class="btn btn-secondary">Edit</a>
        @if ($ticket->status === 'scanning')
            <span class="btn btn-primary" aria-disabled="true">Sedang scan…</span>
        @else
            <form action="{{ route('tickets.scan', $ticket) }}" method="POST" data-busy="Mengantre…">
                @csrf
                <button type="submit" class="btn btn-primary">{{ $ticket->scanned_at ? 'Scan ulang' : 'Jalankan scan' }}</button>
            </form>
        @endif
    </div>
</div>

<div class="detail-grid">
    <div class="stack">
        @if ($ticket->status === 'scanning')
            <div class="card card-pad" role="status" aria-live="polite">
                <div class="row" style="gap:0.75rem">
                    <span class="spinner" aria-hidden="true"></span>
                    <div>
                        <h2>Scan sedang berjalan</h2>
                        <p class="muted" style="margin-top:0.25rem;font-size:0.875rem">
                            Scan berjalan di latar belakang, jadi kamu bebas menutup atau meninggalkan halaman ini.
                            Halaman diperbarui otomatis saat hasilnya siap.
                        </p>
                    </div>
                </div>
            </div>
        @elseif ($ticket->status === 'failed')
            <div class="card card-pad">
                <h2>Scan gagal</h2>
                <p class="muted" style="margin-top:0.35rem">{{ $result['error'] ?? 'Terjadi kesalahan saat menghubungi endpoint.' }}</p>
                <p class="faint" style="margin-top:0.75rem;font-size:0.825rem">Periksa URL lalu coba scan ulang. Kalau URL-nya salah, perbaiki lewat tombol Edit.</p>
            </div>
        @elseif (! $ticket->scanned_at)
            <div class="card empty">
                <h2>Belum pernah di-scan</h2>
                <p>Scan mengecek HTTPS, header keamanan, CORS, cookie, kebocoran versi software, pesan debug, dan kecepatan respons endpoint ini.</p>
                <form action="{{ route('tickets.scan', $ticket) }}" method="POST" data-busy="Sedang scan…">
                    @csrf
                    <button type="submit" class="btn btn-primary">Jalankan scan sekarang</button>
                </form>
            </div>
        @else
            @if ($ticket->score !== null)
            @php
                $score = $ticket->score;
                $tone = $score >= 80 ? 'var(--ok)' : ($score >= 60 ? 'var(--sev-medium)' : 'var(--sev-high)');
            @endphp
            <div class="card card-pad score-card">
                <div class="score-ring" style="--value: {{ $score }}; --tone: {{ $tone }}" role="img"
                    aria-label="Skor keamanan {{ $score }} dari 100, grade {{ $ticket->grade }}">
                    <div class="score-inner">
                        <span class="score-grade" style="color: {{ $tone }}">{{ $ticket->grade }}</span>
                        <span class="score-value">{{ $score }}<span class="score-max">/100</span></span>
                    </div>
                </div>
                <div>
                    <h2>Skor keamanan</h2>
                    <p class="muted" style="margin-top:0.35rem">{{ \App\Services\SecurityScore::verdict($score) }}</p>
                    <div class="row" style="margin-top:0.9rem;gap:1.25rem">
                        @foreach (['critical' => 'Critical', 'high' => 'High', 'medium' => 'Medium', 'low' => 'Low'] as $key => $label)
                            @php $count = collect($ticket->findings ?? [])->where('severity', $key)->count(); @endphp
                            <span class="sev sev-{{ $key }}">{{ $label }} <b style="margin-left:0.25rem;color:var(--ink)">{{ $count }}</b></span>
                        @endforeach
                    </div>
                </div>
            </div>
            @endif

            @if ($changes)
                <div class="card">
                    <div class="card-head">
                        <h2>Perubahan sejak scan sebelumnya</h2>
                        <span class="faint" style="font-size:0.8rem">{{ $changes['since']->diffForHumans() }}</span>
                    </div>
                    @if (empty($changes['fixed']) && empty($changes['new']))
                        <p class="muted" style="padding:0.9rem 1.25rem;font-size:0.875rem">Tidak ada perubahan temuan dibanding scan sebelumnya.</p>
                    @else
                        <div class="changes">
                            <div class="fixed">
                                <h3>✓ Sudah diperbaiki ({{ count($changes['fixed']) }})</h3>
                                <ul>
                                    @forelse ($changes['fixed'] as $title)
                                        <li>{{ $title }}</li>
                                    @empty
                                        <li class="faint">Belum ada</li>
                                    @endforelse
                                </ul>
                            </div>
                            <div class="new">
                                <h3>✕ Temuan baru ({{ count($changes['new']) }})</h3>
                                <ul>
                                    @forelse ($changes['new'] as $title)
                                        <li>{{ $title }}</li>
                                    @empty
                                        <li class="faint">Tidak ada</li>
                                    @endforelse
                                </ul>
                            </div>
                        </div>
                    @endif
                </div>
            @endif

            <div class="card">
                <dl class="summary">
                    <div>
                        <dt>Lolos pemeriksaan</dt>
                        <dd>{{ $passed }} / {{ count($checks) }}</dd>
                    </div>
                    <div>
                        <dt>Status HTTP</dt>
                        <dd class="mono">{{ $result['status_code'] ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt>Waktu respons</dt>
                        <dd>{{ isset($result['response_time_ms']) ? number_format($result['response_time_ms']).' ms' : '—' }}</dd>
                    </div>
                    <div>
                        <dt>Di-scan</dt>
                        <dd title="{{ $ticket->scanned_at->format('d M Y H:i') }}">{{ $ticket->scanned_at->diffForHumans() }}</dd>
                    </div>
                </dl>

                <div class="card-head" style="border-top:0">
                    <h2>Hasil pemeriksaan</h2>
                    <span class="faint" style="font-size:0.8rem">{{ count($ticket->findings ?? []) }} temuan</span>
                </div>

                <ul class="checks">
                    @foreach (collect($checks)->sortBy('passed') as $check)
                        <li class="check-row">
                            <span class="mark {{ $check['passed'] ? 'mark-pass' : 'mark-fail' }}" aria-hidden="true">{{ $check['passed'] ? '✓' : '✕' }}</span>
                            <div>
                                <p class="check-label">
                                    <span class="sr-only">{{ $check['passed'] ? 'Lolos:' : 'Gagal:' }}</span>
                                    {{ $check['label'] }}
                                </p>
                                @if (! $check['passed'] && $check['detail'])
                                    <p class="check-detail">{{ $check['detail'] }}</p>
                                @endif
                            </div>
                            <div>
                                @unless ($check['passed'])
                                    <span class="sev sev-{{ $check['severity'] }}">{{ ucfirst($check['severity']) }}</span>
                                @endunless
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>

            @if (! empty($result['headers']) || ! empty($result['redirect_to']))
                <div class="card">
                    <div class="card-head"><h2>Header respons</h2></div>
                    <dl class="headers">
                        @if (! empty($result['redirect_to']))
                            <div><dt>Location</dt><dd>{{ $result['redirect_to'] }}</dd></div>
                        @endif
                        @foreach ($result['headers'] ?? [] as $name => $value)
                            <div><dt>{{ $name }}</dt><dd>{{ $value }}</dd></div>
                        @endforeach
                    </dl>
                </div>
            @endif
        @endif

        @if ($history->count() > 1)
            <div class="card">
                <div class="card-head">
                    <h2>Riwayat scan</h2>
                    <span class="row" style="gap:1rem;font-size:0.8rem">
                        <a class="link" href="{{ route('tickets.compare', $ticket) }}">Bandingkan</a>
                        @if ($historyTotal > $history->count())
                            <a class="link" href="{{ route('tickets.scans', $ticket) }}">Lihat semua {{ $historyTotal }}</a>
                        @else
                            <span class="faint">{{ $historyTotal }} scan</span>
                        @endif
                    </span>
                </div>
                @php
                    $trend = $history->reverse()->values();
                    $maxFindings = max(1, $trend->max(fn ($scan) => count($scan->findings ?? [])));
                @endphp
                <div class="trend" role="img" aria-label="Tren jumlah temuan dari scan lama ke terbaru">
                    @foreach ($trend as $scan)
                        <div class="trend-col" title="{{ $scan->created_at->translatedFormat('d M H:i') }} — {{ $scan->status === 'failed' ? 'gagal' : 'skor '.$scan->score.' ('.$scan->grade.')' }}">
                            <span style="height: {{ $scan->status === 'failed' ? 100 : max(8, $scan->score ?? 0) }}%;
                                background: {{ $scan->status === 'failed' ? 'var(--line-strong)' : (($scan->score ?? 0) >= 80 ? 'var(--ok)' : (($scan->score ?? 0) >= 60 ? 'var(--sev-medium)' : 'var(--sev-high)')) }}"></span>
                        </div>
                    @endforeach
                </div>

                <ul class="history">
                    @foreach ($history as $scan)
                        <li>
                            <span class="when" title="{{ $scan->created_at->format('d M Y H:i') }}">
                                {{ $scan->created_at->translatedFormat('d M Y, H:i') }}
                                @if ($loop->first)<span class="latest">terbaru</span>@endif
                            </span>
                            <span class="row" style="gap:0.9rem">
                                @if ($scan->status === 'failed')
                                    <span class="badge badge-failed">Gagal</span>
                                @else
                                    <span class="faint">skor {{ $scan->score }} · {{ count($scan->findings ?? []) }} temuan</span>
                                    @if ($scan->severity)
                                        <span class="sev sev-{{ $scan->severity }}">{{ ucfirst($scan->severity) }}</span>
                                    @else
                                        <span class="badge badge-completed">Bersih</span>
                                    @endif
                                @endif
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>

    <aside class="stack">
        <div class="card card-pad">
            <h2 style="margin-bottom:0.5rem">Detail</h2>
            <ul class="meta">
                <li><span>ID</span><span class="mono">#{{ $ticket->id }}</span></li>
                <li><span>Dibuat</span><span>{{ $ticket->created_at->format('d M Y') }}</span></li>
                <li><span>Diubah</span><span>{{ $ticket->updated_at->diffForHumans() }}</span></li>
                @if (! empty($result['ip']))
                    <li><span>IP target</span><span class="mono">{{ $result['ip'] }}</span></li>
                @endif
            </ul>
            @if ($ticket->description)
                <p class="muted" style="margin-top:0.9rem;font-size:0.875rem;white-space:pre-line">{{ $ticket->description }}</p>
            @endif
        </div>

        <div class="card card-pad">
            <h2>Monitoring otomatis</h2>
            <p class="muted" style="margin:0.35rem 0 0.9rem;font-size:0.85rem">
                @if ($ticket->auto_scan)
                    Aktif. Ticket ini di-scan ulang tiap hari, dan kamu diemail kalau ada temuan high atau critical baru.
                @else
                    Scan ulang otomatis setiap hari, plus email peringatan kalau muncul temuan berisiko tinggi.
                @endif
            </p>
            <form action="{{ route('tickets.monitoring', $ticket) }}" method="POST">
                @csrf
                @method('PATCH')
                <input type="hidden" name="auto_scan" value="{{ $ticket->auto_scan ? 0 : 1 }}">
                <button type="submit" class="btn {{ $ticket->auto_scan ? 'btn-secondary' : 'btn-primary' }} btn-block">
                    {{ $ticket->auto_scan ? 'Matikan monitoring' : 'Aktifkan monitoring' }}
                </button>
            </form>
        </div>

        @if ($ticket->status === 'completed')
        <div class="card card-pad">
            <h2>Bagikan laporan</h2>
            @if ($ticket->share_token)
                <p class="muted" style="margin:0.35rem 0 0.7rem;font-size:0.85rem">Aktif. Siapa pun yang punya link ini bisa melihat laporannya tanpa login.</p>
                <input class="input mono" style="font-size:0.75rem" type="text" readonly
                    value="{{ route('tickets.public', $ticket->share_token) }}"
                    onclick="this.select()" aria-label="Link publik laporan">
                <p class="hint" style="margin-top:0.9rem">Badge untuk README:</p>
                <img src="{{ route('tickets.badge', $ticket->share_token) }}" alt="Badge skor keamanan" style="margin-top:0.4rem">
                <input class="input mono" style="margin-top:0.4rem;font-size:0.7rem" type="text" readonly
                    value="[![API security]({{ route('tickets.badge', $ticket->share_token) }})]({{ route('tickets.public', $ticket->share_token) }})"
                    onclick="this.select()" aria-label="Kode Markdown badge">

                <form action="{{ route('tickets.share', $ticket) }}" method="POST" style="margin-top:0.6rem">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="share" value="0">
                    <button type="submit" class="btn btn-ghost btn-block">Matikan link</button>
                </form>
            @else
                <p class="muted" style="margin:0.35rem 0 0.9rem;font-size:0.85rem">Buat link rahasia supaya tim atau klien bisa melihat hasil ini tanpa perlu akun.</p>
                <form action="{{ route('tickets.share', $ticket) }}" method="POST">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="share" value="1">
                    <button type="submit" class="btn btn-secondary btn-block">Buat link publik</button>
                </form>
            @endif
        </div>
        @endif

        <div class="card card-pad">
            <h2>Laporan</h2>
            <p class="muted" style="margin:0.35rem 0 0.9rem;font-size:0.85rem">Cetak atau simpan sebagai PDF untuk dibagikan, atau unduh JSON untuk arsip dan otomasi.</p>
            @if ($ticket->scanned_at && $ticket->status === 'completed')
                <a href="{{ route('tickets.print', $ticket) }}" class="btn btn-secondary btn-block" target="_blank" rel="noopener">Cetak / simpan PDF</a>
                <a href="{{ route('tickets.report', $ticket) }}" class="btn btn-ghost btn-block" style="margin-top:0.4rem">Unduh JSON</a>
            @else
                <span class="btn btn-secondary btn-block" aria-disabled="true">Belum ada hasil scan</span>
            @endif
        </div>

        <div class="card card-pad danger-zone">
            <h2>Hapus ticket</h2>
            <p class="muted" style="margin:0.35rem 0 0.9rem;font-size:0.85rem">Ticket dan semua hasil scan-nya akan dihapus permanen.</p>
            <form action="{{ route('tickets.destroy', $ticket) }}" method="POST" data-confirm="Hapus ticket &quot;{{ $ticket->title }}&quot;? Tindakan ini tidak bisa dibatalkan.">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-danger btn-block">Hapus ticket</button>
            </form>
        </div>
    </aside>
</div>
@endsection
