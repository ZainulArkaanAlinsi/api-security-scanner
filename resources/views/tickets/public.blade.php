@php
    $result = $ticket->scan_result ?? [];
    $checks = collect($result['checks'] ?? []);
    $passed = $checks->where('passed', true)->count();
    $tone = $ticket->score >= 80 ? 'var(--ok)' : ($ticket->score >= 60 ? 'var(--sev-medium)' : 'var(--sev-high)');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Laporan keamanan — {{ $ticket->title }}</title>

    @include('partials.theme')

    <style>
        .wrap { max-width: 760px; margin: 0 auto; padding: 2rem clamp(1rem, 4vw, 2rem) 4rem; }
        .top { display: flex; justify-content: space-between; align-items: center; gap: 1rem; margin-bottom: 2.5rem; }
        .hero { display: flex; align-items: center; gap: 1.5rem; margin-bottom: 1.25rem; }
        .ring {
            flex: none; width: 104px; height: 104px; border-radius: 50%; display: grid; place-items: center;
            background: conic-gradient(var(--tone) calc(var(--value) * 1%), var(--surface-2) 0);
        }
        .ring-inner { width: 82px; height: 82px; border-radius: 50%; background: var(--surface); display: grid; place-items: center; line-height: 1.1; }
        .ring-grade { font-size: 1.8rem; font-weight: 600; }
        .ring-score { font-size: 0.75rem; color: var(--ink-soft); }
        .summary { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 0 1.5rem; border-top: 1px solid var(--line); }
        .summary > div { padding: 0.9rem 0; }
        .summary dt { font-size: 0.75rem; color: var(--ink-faint); }
        .summary dd { margin-top: 0.2rem; font-weight: 500; }
        .finding { padding: 0.9rem 1.25rem; border-bottom: 1px solid var(--line); }
        .finding:last-child { border-bottom: 0; }
        .finding-head { display: flex; justify-content: space-between; gap: 1rem; align-items: baseline; }
        .finding p { margin-top: 0.3rem; font-size: 0.85rem; color: var(--ink-soft); }
        .passed-list { columns: 2; column-gap: 1.5rem; padding: 1rem 1.25rem; list-style: none; font-size: 0.85rem; }
        .passed-list li { padding: 0.2rem 0; break-inside: avoid; color: var(--ink-soft); }
        .passed-list li::before { content: '✓'; color: var(--ok); margin-right: 0.45rem; }
        .foot { margin-top: 2.5rem; font-size: 0.8rem; color: var(--ink-faint); text-align: center; }
        @media (max-width: 560px) {
            .hero { flex-direction: column; align-items: flex-start; }
            .summary { grid-template-columns: 1fr; }
            .passed-list { columns: 1; }
        }
    </style>
</head>

<body>
    <div class="wrap">
        <header class="top">
            <span class="brand">
                <span class="brand-mark" aria-hidden="true"></span>
                API Scanner
            </span>
            <span class="badge">Laporan dibagikan</span>
        </header>

        <p class="eyebrow">Laporan keamanan API</p>
        <h1 style="margin-top:0.6rem;overflow-wrap:anywhere">{{ $ticket->title }}</h1>
        <p class="mono muted" style="margin-top:0.35rem;overflow-wrap:anywhere">{{ $ticket->api_url }}</p>

        <div class="card card-pad hero" style="margin-top:1.75rem">
            <div class="ring" style="--value: {{ $ticket->score }}; --tone: {{ $tone }}" role="img"
                aria-label="Skor {{ $ticket->score }} dari 100, grade {{ $ticket->grade }}">
                <div class="ring-inner">
                    <span class="ring-grade" style="color: {{ $tone }}">{{ $ticket->grade }}</span>
                    <span class="ring-score">{{ $ticket->score }}/100</span>
                </div>
            </div>
            <div>
                <h2>{{ \App\Services\SecurityScore::verdict($ticket->score) }}</h2>
                <dl class="summary" style="margin-top:0.75rem">
                    <div>
                        <dt>Lolos pemeriksaan</dt>
                        <dd>{{ $passed }} / {{ $checks->count() }}</dd>
                    </div>
                    <div>
                        <dt>Temuan</dt>
                        <dd>{{ count($ticket->findings ?? []) }}</dd>
                    </div>
                    <div>
                        <dt>Tanggal scan</dt>
                        <dd>{{ $ticket->scanned_at->translatedFormat('d M Y') }}</dd>
                    </div>
                </dl>
            </div>
        </div>

        @if (count($ticket->findings ?? []) > 0)
            <div class="card" style="margin-top:1.25rem">
                <div class="card-head"><h2>Temuan</h2></div>
                @foreach ($ticket->findings as $finding)
                    <div class="finding">
                        <div class="finding-head">
                            <strong>{{ $finding['title'] }}</strong>
                            <span class="sev sev-{{ $finding['severity'] }}">{{ ucfirst($finding['severity']) }}</span>
                        </div>
                        <p>{{ $finding['detail'] }}</p>
                    </div>
                @endforeach
            </div>
        @else
            <div class="card card-pad" style="margin-top:1.25rem">
                <h2 style="color:var(--ok)">Tidak ada temuan</h2>
                <p class="muted" style="margin-top:0.35rem;font-size:0.9rem">Semua pemeriksaan lolos pada saat scan dijalankan.</p>
            </div>
        @endif

        @if ($checks->where('passed', true)->isNotEmpty())
            <div class="card" style="margin-top:1.25rem">
                <div class="card-head"><h2>Pemeriksaan yang lolos</h2></div>
                <ul class="passed-list">
                    @foreach ($checks->where('passed', true) as $check)
                        <li>{{ $check['label'] }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <p class="foot">
            Laporan ini mencerminkan kondisi endpoint saat scan dijalankan.<br>
            Dibuat dengan <a class="link" href="{{ route('home') }}">API Scanner</a>.
        </p>
    </div>
</body>

</html>
