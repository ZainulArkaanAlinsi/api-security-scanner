@php
    $result = $ticket->scan_result ?? [];
    $checks = collect($result['checks'] ?? []);
    $findings = $ticket->findings ?? [];
    $passedChecks = $checks->where('passed', true);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laporan audit - {{ $ticket->title }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600|jetbrains-mono:400" rel="stylesheet" />

    <style>
        @page { size: A4; margin: 16mm 14mm; }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', ui-sans-serif, system-ui, sans-serif;
            font-size: 13px;
            line-height: 1.55;
            color: #1c1917;
            background: #e7e5e4;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            max-width: 210mm;
            margin: 1.25rem auto 0.75rem;
            padding: 0 1rem;
            font-size: 0.85rem;
            color: #57534e;
        }

        .toolbar button, .toolbar a {
            height: 34px;
            padding: 0 0.9rem;
            font: inherit;
            font-weight: 500;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }
        .toolbar button { color: #fafaf9; background: #1c1917; border: 0; }
        .toolbar a { color: #1c1917; border: 1px solid #d6d3d1; background: #fff; }

        .sheet {
            max-width: 210mm;
            margin: 0 auto 2rem;
            padding: 16mm 14mm;
            background: #fff;
        }

        .mono { font-family: 'JetBrains Mono', ui-monospace, monospace; font-size: 0.92em; }
        .muted { color: #57534e; }

        header { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; padding-bottom: 14px; border-bottom: 2px solid #1c1917; }
        .brand { font-weight: 600; font-size: 13px; }
        .doc-type { font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: #78716c; text-align: right; }

        h1 { margin-top: 20px; font-size: 22px; font-weight: 600; letter-spacing: -0.02em; line-height: 1.25; }
        .target { margin-top: 4px; overflow-wrap: anywhere; }

        .summary { display: grid; grid-template-columns: repeat(4, 1fr); margin-top: 18px; border: 1px solid #e7e5e4; border-radius: 6px; }
        .summary div { padding: 10px 12px; }
        .summary div + div { border-left: 1px solid #e7e5e4; }
        .summary dt { font-size: 10.5px; color: #78716c; }
        .summary dd { margin-top: 2px; font-size: 14px; font-weight: 600; }

        h2 { margin-top: 26px; margin-bottom: 8px; font-size: 13px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; }

        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; font-size: 10.5px; font-weight: 500; color: #78716c; padding: 6px 8px; border-bottom: 1px solid #d6d3d1; }
        td { padding: 9px 8px; border-bottom: 1px solid #e7e5e4; vertical-align: top; }
        tr { break-inside: avoid; }
        .sev { display: inline-block; min-width: 58px; padding: 1px 7px; font-size: 10.5px; font-weight: 600; text-transform: uppercase; border-radius: 3px; text-align: center; }
        .sev-critical { background: #fce7f3; color: #9f1239; }
        .sev-high { background: #fee2e2; color: #991b1b; }
        .sev-medium { background: #fef3c7; color: #92400e; }
        .sev-low { background: #f5f5f4; color: #57534e; }
        .finding-title { font-weight: 500; }
        .finding-detail { margin-top: 2px; color: #57534e; }

        .passed { columns: 2; column-gap: 24px; list-style: none; }
        .passed li { padding: 3px 0; break-inside: avoid; }
        .passed li::before { content: '✓'; color: #15803d; font-weight: 700; margin-right: 6px; }

        .clean { padding: 12px; border: 1px solid #bbf7d0; background: #f0fdf4; color: #166534; border-radius: 6px; }

        footer { margin-top: 30px; padding-top: 10px; border-top: 1px solid #e7e5e4; font-size: 10.5px; color: #78716c; display: flex; justify-content: space-between; gap: 1rem; }

        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { margin: 0; padding: 0; max-width: none; }
        }

        @media (max-width: 640px) {
            .sheet { padding: 1.25rem; }
            .summary { grid-template-columns: repeat(2, 1fr); }
            .summary div:nth-child(3) { border-left: 0; }
            .summary div:nth-child(n+3) { border-top: 1px solid #e7e5e4; }
            .passed { columns: 1; }
        }
    </style>
</head>

<body>
    <div class="toolbar">
        <a href="{{ route('tickets.show', $ticket) }}">← Kembali</a>
        <span>Pilih "Save as PDF" di dialog cetak untuk menyimpan file.</span>
        <button type="button" onclick="window.print()">Cetak / simpan PDF</button>
    </div>

    <article class="sheet">
        <header>
            <div class="brand">API Scanner</div>
            <div class="doc-type">Laporan audit keamanan API<br><span class="mono">#{{ $ticket->id }}</span></div>
        </header>

        <h1>{{ $ticket->title }}</h1>
        <p class="target mono muted">{{ $ticket->api_url }}</p>
        @if ($ticket->description)
            <p class="muted" style="margin-top:6px">{{ $ticket->description }}</p>
        @endif

        <dl class="summary">
            <div>
                <dt>Skor keamanan</dt>
                <dd>{{ $ticket->score }}/100 · Grade {{ $ticket->grade }}</dd>
            </div>
            <div>
                <dt>Lolos pemeriksaan</dt>
                <dd>{{ $passedChecks->count() }} / {{ $checks->count() }}</dd>
            </div>
            <div>
                <dt>Status HTTP · waktu respons</dt>
                <dd>{{ $result['status_code'] ?? '—' }} · {{ isset($result['response_time_ms']) ? number_format($result['response_time_ms']).' ms' : '—' }}</dd>
            </div>
            <div>
                <dt>Tanggal scan</dt>
                <dd>{{ $ticket->scanned_at->translatedFormat('d M Y, H:i') }}</dd>
            </div>
        </dl>

        <h2>Temuan ({{ count($findings) }})</h2>
        @if (count($findings) === 0)
            <p class="clean">Tidak ada temuan. Semua pemeriksaan lolos pada saat scan dijalankan.</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th style="width:72px">Risiko</th>
                        <th>Temuan & rekomendasi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($findings as $finding)
                        <tr>
                            <td><span class="sev sev-{{ $finding['severity'] }}">{{ $finding['severity'] }}</span></td>
                            <td>
                                <p class="finding-title">{{ $finding['title'] }}</p>
                                <p class="finding-detail">{{ $finding['detail'] }}</p>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @if ($passedChecks->isNotEmpty())
            <h2>Pemeriksaan yang lolos ({{ $passedChecks->count() }})</h2>
            <ul class="passed">
                @foreach ($passedChecks as $check)
                    <li>{{ $check['label'] }}</li>
                @endforeach
            </ul>
        @endif

        @if (isset($result['certificate_days_left']))
            <p class="muted" style="margin-top:14px">Sertifikat TLS berlaku {{ $result['certificate_days_left'] }} hari lagi.</p>
        @endif

        <footer>
            <span>Dibuat {{ now()->translatedFormat('d M Y, H:i') }} oleh {{ auth()->user()->name }}</span>
            <span>Hasil mencerminkan kondisi endpoint saat scan dijalankan.</span>
        </footer>
    </article>
</body>

</html>
