<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') - API Scanner</title>

    @include('partials.theme')

    <style>
        .container { max-width: 1120px; margin: 0 auto; padding: 0 clamp(1rem, 4vw, 2rem); }

        .topbar {
            position: sticky;
            top: 0;
            z-index: 20;
            background: color-mix(in srgb, var(--bg) 92%, transparent);
            backdrop-filter: saturate(1.4) blur(6px);
            border-bottom: 1px solid var(--line);
        }

        .topbar-inner { display: flex; align-items: center; gap: 1.5rem; height: 56px; }

        .nav { display: flex; gap: 0.25rem; }
        .nav a {
            padding: 0.4rem 0.7rem;
            font-size: 0.875rem;
            color: var(--ink-soft);
            text-decoration: none;
            border-radius: 6px;
        }
        .nav a:hover { color: var(--ink); background: var(--surface-2); }
        .nav a[aria-current=page] { color: var(--ink); font-weight: 500; }

        .topbar-right { margin-left: auto; display: flex; align-items: center; gap: 0.5rem; }

        .icon-btn {
            display: inline-grid;
            place-items: center;
            width: 34px;
            height: 34px;
            color: var(--ink-soft);
            background: transparent;
            border: 1px solid transparent;
            border-radius: 8px;
            cursor: pointer;
        }
        .icon-btn:hover { color: var(--ink); background: var(--surface-2); }
        .icon-btn svg { width: 17px; height: 17px; }
        .icon-sun { display: none; }
        .dark .icon-sun { display: block; }
        .dark .icon-moon { display: none; }

        .menu { position: relative; }
        .menu summary {
            list-style: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.2rem 0.45rem 0.2rem 0.2rem;
            border-radius: 999px;
            cursor: pointer;
        }
        .menu summary::-webkit-details-marker { display: none; }
        .menu summary:hover { background: var(--surface-2); }
        .avatar {
            display: grid;
            place-items: center;
            width: 28px;
            height: 28px;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--btn-ink);
            background: var(--btn-bg);
            border-radius: 50%;
        }
        .menu-name { font-size: 0.85rem; max-width: 140px; }
        .menu-panel {
            position: absolute;
            right: 0;
            top: calc(100% + 6px);
            width: 230px;
            padding: 0.35rem;
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 10px;
            box-shadow: 0 12px 32px -12px rgb(0 0 0 / 0.25);
        }
        .menu-head { padding: 0.55rem 0.65rem 0.65rem; border-bottom: 1px solid var(--line); margin-bottom: 0.35rem; }
        .menu-head p { font-size: 0.85rem; }
        .menu-item {
            display: block;
            width: 100%;
            padding: 0.5rem 0.65rem;
            font: inherit;
            font-size: 0.875rem;
            text-align: left;
            color: var(--ink);
            text-decoration: none;
            background: transparent;
            border: 0;
            border-radius: 6px;
            cursor: pointer;
        }
        .menu-item:hover { background: var(--surface-2); }

        main { padding: 2rem 0 4rem; }

        .page-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            gap: 1rem;
            flex-wrap: wrap;
            margin-bottom: 1.5rem;
        }
        .page-head p { margin-top: 0.3rem; color: var(--ink-soft); }

        .back {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            margin-bottom: 1rem;
            font-size: 0.85rem;
            color: var(--ink-soft);
            text-decoration: none;
        }
        .back:hover { color: var(--ink); }
        .back svg { width: 14px; height: 14px; }

        .flash { margin-bottom: 1.25rem; }

        .footer { border-top: 1px solid var(--line); padding: 1.25rem 0; font-size: 0.8rem; color: var(--ink-faint); }

        @media (max-width: 640px) {
            .menu-name { display: none; }
            .topbar-inner { gap: 0.75rem; }
            .nav a { padding: 0.4rem 0.5rem; }
        }
    </style>

    @stack('styles')
</head>

<body>
    <a href="#main" class="skip-link">Lewati ke konten</a>

    <header class="topbar">
        <div class="container topbar-inner">
            <a href="{{ route('tickets.index') }}" class="brand">
                <span class="brand-mark" aria-hidden="true"></span>
                <span>API Scanner</span>
            </a>

            <nav class="nav" aria-label="Utama">
                <a href="{{ route('tickets.index') }}" @if (request()->routeIs('tickets.index', 'tickets.show', 'tickets.edit')) aria-current="page" @endif>Dashboard</a>
                <a href="{{ route('tickets.create') }}" @if (request()->routeIs('tickets.create')) aria-current="page" @endif>Scan baru</a>
            </nav>

            <div class="topbar-right">
                <button type="button" class="icon-btn" id="themeToggle" aria-label="Ganti tema terang/gelap">
                    <svg class="icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z"/></svg>
                    <svg class="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
                </button>

                <details class="menu">
                    <summary aria-label="Menu akun">
                        <span class="avatar" aria-hidden="true">{{ strtoupper(mb_substr(Auth::user()->name, 0, 1)) }}</span>
                        <span class="menu-name truncate">{{ Auth::user()->name }}</span>
                    </summary>
                    <div class="menu-panel">
                        <div class="menu-head">
                            <p class="truncate">{{ Auth::user()->name }}</p>
                            <p class="faint truncate" style="font-size:0.8rem">{{ Auth::user()->email }}</p>
                        </div>
                        <a class="menu-item" href="{{ route('profile.edit') }}">Profil & keamanan</a>
                        <form action="{{ route('logout') }}" method="POST">
                            @csrf
                            <button type="submit" class="menu-item">Keluar</button>
                        </form>
                    </div>
                </details>
            </div>
        </div>
    </header>

    <main id="main">
        <div class="container">
            @if (session('success'))
                <div class="alert alert-success flash" role="status">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="alert alert-error flash" role="alert">{{ session('error') }}</div>
            @endif

            @yield('content')
        </div>
    </main>

    <p id="busy-status" class="sr-only" role="status" aria-live="polite"></p>

    <footer class="footer">
        <div class="container">&copy; {{ date('Y') }} API Scanner</div>
    </footer>

    <script>
        document.getElementById('themeToggle').addEventListener('click', () => {
            const dark = document.documentElement.classList.toggle('dark');
            localStorage.theme = dark ? 'dark' : 'light';
        });

        // Close the account menu when clicking elsewhere.
        document.addEventListener('click', (event) => {
            document.querySelectorAll('details.menu[open]').forEach((menu) => {
                if (!menu.contains(event.target)) menu.removeAttribute('open');
            });
        });

        // ...and on Escape, returning focus to the trigger.
        document.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape') return;
            document.querySelectorAll('details.menu[open]').forEach((menu) => {
                menu.removeAttribute('open');
                menu.querySelector('summary')?.focus();
            });
        });

        document.querySelectorAll('form[data-confirm]').forEach((form) => {
            form.addEventListener('submit', (event) => {
                if (!confirm(form.dataset.confirm)) event.preventDefault();
            });
        });

        // Keep the button focusable while it works: disabling it would drop focus
        // to <body>, and a disabled control is not announced reliably.
        document.querySelectorAll('form[data-busy]').forEach((form) => {
            form.addEventListener('submit', () => {
                const button = form.querySelector('button[type=submit]');
                if (!button) return;

                button.setAttribute('aria-disabled', 'true');
                button.style.pointerEvents = 'none';
                button.style.opacity = '0.6';
                button.textContent = form.dataset.busy;

                const status = document.getElementById('busy-status');
                if (status) status.textContent = form.dataset.busy;
            });
        });
    </script>

    @stack('scripts')
</body>

</html>
