<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') - API Scanner</title>

    @include('partials.theme')

    <style>
        .wrap {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            padding: 2rem clamp(1.25rem, 5vw, 4rem);
        }
        .body { flex: 1; display: flex; align-items: center; }
        .inner { max-width: 440px; }
        .code {
            display: inline-block;
            margin-bottom: 1.25rem;
            padding: 0.2rem 0.55rem;
            font-family: var(--mono);
            font-size: 0.8rem;
            color: var(--ink-soft);
            border: 1px solid var(--line-strong);
            border-radius: 6px;
        }
        .inner p { margin-top: 0.6rem; color: var(--ink-soft); }
        .inner .row { margin-top: 1.75rem; }
    </style>
</head>

<body>
    <div class="wrap">
        <a href="{{ url('/') }}" class="brand">
            <span class="brand-mark" aria-hidden="true"></span>
            API Scanner
        </a>
        <div class="body">
            <div class="inner">
                <span class="code">HTTP @yield('code')</span>
                <h1>@yield('title')</h1>
                <p>@yield('message')</p>
                <div class="row">
                    @hasSection('actions')
                        @yield('actions')
                    @else
                        <a href="{{ url('/') }}" class="btn btn-primary">Ke halaman utama</a>
                        <button type="button" class="btn btn-ghost" onclick="history.back()">Kembali</button>
                    @endif
                </div>
            </div>
        </div>
    </div>
</body>

</html>
