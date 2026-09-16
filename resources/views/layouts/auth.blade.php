<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') - API Scanner</title>

    @include('partials.theme')

    <style>
        .shell {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 1.1fr);
            min-height: 100vh;
        }

        .pane {
            display: flex;
            flex-direction: column;
            padding: 2rem clamp(1.25rem, 5vw, 4rem);
        }

        .form-wrap {
            flex: 1;
            display: flex;
            align-items: center;
            padding: 2.5rem 0;
        }

        .form-inner { width: 100%; max-width: 360px; }

        .lede { margin-top: 0.5rem; color: var(--ink-soft); font-size: 0.95rem; }

        .form-inner form { margin-top: 1.75rem; }
        .form-inner .alert { margin-top: 1.5rem; }

        .switch { margin-top: 1.5rem; font-size: 0.875rem; color: var(--ink-soft); }

        .foot { font-size: 0.8rem; color: var(--ink-faint); }

        .preview {
            background: #0c0a09;
            color: #e7e5e4;
            padding: 2rem clamp(1.5rem, 4vw, 3.5rem);
            display: flex;
            flex-direction: column;
            justify-content: center;
            border-left: 1px solid var(--line);
        }

        .dark .preview { background: #151312; }

        .preview-inner { max-width: 520px; }
        .preview .eyebrow { color: #a8a29e; }

        .preview-title {
            margin: 0.75rem 0 1.75rem;
            font-size: 1.35rem;
            font-weight: 500;
            letter-spacing: -0.02em;
            line-height: 1.35;
            color: #fafaf9;
        }

        .caption { margin-top: 0.9rem; font-size: 0.75rem; color: #57534e; }

        @media (max-width: 900px) {
            .shell { grid-template-columns: 1fr; }
            .preview { display: none; }
        }
    </style>
</head>

<body>
    <div class="shell">
        <section class="pane">
            <a href="{{ route('home') }}" class="brand">
                <span class="brand-mark" aria-hidden="true"></span>
                API Scanner
            </a>

            <div class="form-wrap">
                <div class="form-inner">
                    @yield('content')
                </div>
            </div>

            <p class="foot">&copy; {{ date('Y') }} API Scanner</p>
        </section>

        <aside class="preview" aria-hidden="true">
            <div class="preview-inner">
                <p class="eyebrow">Security audit</p>
                <p class="preview-title">Setiap endpoint dicek, setiap temuan dicatat dengan tingkat risikonya.</p>
                @include('partials.scan-preview')
                <p class="caption">Contoh tampilan hasil scan.</p>
            </div>
        </aside>
    </div>

    <script>
        document.querySelectorAll('[data-toggle-pass]').forEach((btn) => {
            const input = document.getElementById(btn.dataset.togglePass);
            btn.addEventListener('click', () => {
                const show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                btn.textContent = show ? 'Sembunyi' : 'Lihat';
                btn.setAttribute('aria-pressed', String(show));
            });
        });
    </script>
</body>

</html>
