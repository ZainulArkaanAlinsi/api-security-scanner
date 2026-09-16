<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=inter:400,500,600|jetbrains-mono:400,500" rel="stylesheet" />

<script>
    if (localStorage.theme === 'dark' || (!localStorage.theme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        document.documentElement.classList.add('dark');
    }
</script>

<style>
    :root {
        --bg: #fafaf9;
        --surface: #ffffff;
        --surface-2: #f5f5f4;
        --ink: #1c1917;
        --ink-soft: #57534e;
        --ink-faint: #a8a29e;
        --line: #e7e5e4;
        --line-strong: #d6d3d1;
        --accent: #2563eb;
        --danger: #dc2626;
        --danger-bg: #fef2f2;
        --warn: #b45309;
        --warn-bg: #fffbeb;
        --ok: #15803d;
        --ok-bg: #f0fdf4;
        --info: #1d4ed8;
        --info-bg: #eff6ff;
        --btn-bg: #1c1917;
        --btn-ink: #fafaf9;
        --sev-low: #a8a29e;
        --sev-medium: #d97706;
        --sev-high: #dc2626;
        --sev-critical: #9f1239;
        --radius: 8px;
        --mono: 'JetBrains Mono', ui-monospace, SFMono-Regular, Menlo, monospace;
    }

    .dark {
        --bg: #0c0a09;
        --surface: #1c1917;
        --surface-2: #171412;
        --ink: #f5f5f4;
        --ink-soft: #a8a29e;
        --ink-faint: #78716c;
        --line: #292524;
        --line-strong: #44403c;
        --accent: #60a5fa;
        --danger: #f87171;
        --danger-bg: #2a1215;
        --warn: #fbbf24;
        --warn-bg: #2a2010;
        --ok: #4ade80;
        --ok-bg: #0f2418;
        --info: #93c5fd;
        --info-bg: #0f1d33;
        --btn-bg: #f5f5f4;
        --btn-ink: #0c0a09;
        --sev-low: #78716c;
        --sev-medium: #fbbf24;
        --sev-high: #f87171;
        --sev-critical: #fb7185;
    }

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    html { -webkit-text-size-adjust: 100%; }

    body {
        font-family: 'Inter', ui-sans-serif, system-ui, sans-serif;
        font-size: 15px;
        line-height: 1.55;
        background: var(--bg);
        color: var(--ink);
        min-height: 100vh;
        -webkit-font-smoothing: antialiased;
    }

    a { color: inherit; }
    .link { color: var(--accent); text-decoration: none; font-weight: 500; }
    .link:hover { text-decoration: underline; }

    :focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }

    .mono { font-family: var(--mono); font-size: 0.85em; }
    .muted { color: var(--ink-soft); }
    .faint { color: var(--ink-faint); }
    .truncate { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .sr-only { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; }

    /* Brand */
    .brand {
        display: inline-flex;
        align-items: center;
        gap: 0.6rem;
        font-weight: 600;
        font-size: 0.95rem;
        letter-spacing: -0.01em;
        color: var(--ink);
        text-decoration: none;
    }

    .brand-mark {
        width: 22px;
        height: 22px;
        border-radius: 5px;
        background: var(--ink);
        position: relative;
        flex: none;
    }

    .brand-mark::after {
        content: '';
        position: absolute;
        top: 6px;
        right: 6px;
        width: 6px;
        height: 6px;
        border-radius: 1px;
        background: var(--accent);
    }

    /* Typography */
    h1, .h1 { font-size: 1.6rem; font-weight: 600; letter-spacing: -0.025em; line-height: 1.2; }
    h2, .h2 { font-size: 1rem; font-weight: 600; letter-spacing: -0.01em; }
    .eyebrow {
        font-family: var(--mono);
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: var(--ink-faint);
    }

    /* Buttons */
    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.45rem;
        height: 38px;
        padding: 0 0.95rem;
        font: inherit;
        font-size: 0.875rem;
        font-weight: 500;
        white-space: nowrap;
        text-decoration: none;
        border-radius: var(--radius);
        border: 1px solid transparent;
        cursor: pointer;
        transition: background-color 0.12s, border-color 0.12s, opacity 0.12s;
    }

    .btn-primary { background: var(--btn-bg); color: var(--btn-ink); }
    .btn-primary:hover { opacity: 0.88; }
    .btn-secondary { background: var(--surface); color: var(--ink); border-color: var(--line-strong); }
    .btn-secondary:hover { background: var(--surface-2); }
    .btn-danger { background: var(--danger); color: #fff; }
    .dark .btn-danger { color: #0c0a09; }
    .btn-danger:hover { opacity: 0.9; }
    .btn-ghost { background: transparent; color: var(--ink-soft); }
    .btn-ghost:hover { color: var(--ink); background: var(--surface-2); }
    .btn-sm { height: 32px; padding: 0 0.7rem; font-size: 0.8rem; }
    .btn-block { width: 100%; }
    .btn[aria-disabled=true], .btn:disabled { opacity: 0.45; pointer-events: none; }
    .btn svg { width: 15px; height: 15px; flex: none; }

    /* Forms */
    .field + .field { margin-top: 1.1rem; }

    .label {
        display: flex;
        justify-content: space-between;
        align-items: baseline;
        gap: 1rem;
        margin-bottom: 0.4rem;
        font-size: 0.825rem;
        font-weight: 500;
        color: var(--ink);
    }

    .label .link { font-size: 0.8rem; }

    .input {
        width: 100%;
        height: 42px;
        padding: 0 0.8rem;
        font: inherit;
        font-size: 0.925rem;
        color: var(--ink);
        background: var(--surface);
        border: 1px solid var(--line-strong);
        border-radius: var(--radius);
        transition: border-color 0.12s, box-shadow 0.12s;
    }

    textarea.input { height: auto; min-height: 96px; padding: 0.65rem 0.8rem; resize: vertical; }
    select.input { padding-right: 2rem; appearance: none; background-image: linear-gradient(45deg, transparent 50%, var(--ink-faint) 50%), linear-gradient(135deg, var(--ink-faint) 50%, transparent 50%); background-position: calc(100% - 16px) 18px, calc(100% - 11px) 18px; background-size: 5px 5px; background-repeat: no-repeat; }
    .input::placeholder { color: var(--ink-faint); }
    .input:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px color-mix(in srgb, var(--accent) 18%, transparent); }
    .has-error .input { border-color: var(--danger); }

    .hint { margin-top: 0.4rem; font-size: 0.8rem; color: var(--ink-faint); }
    .error { margin-top: 0.4rem; font-size: 0.8rem; color: var(--danger); }

    .input-box { position: relative; }
    .input-box .input { padding-right: 5rem; }
    .toggle-pass {
        position: absolute;
        right: 5px;
        top: 50%;
        transform: translateY(-50%);
        height: 30px;
        padding: 0 0.6rem;
        font: inherit;
        font-size: 0.75rem;
        font-weight: 500;
        color: var(--ink-soft);
        background: transparent;
        border: 0;
        border-radius: 6px;
        cursor: pointer;
    }
    .toggle-pass:hover { color: var(--ink); background: var(--surface-2); }

    .check {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.85rem;
        color: var(--ink-soft);
        cursor: pointer;
    }
    .check input { width: 15px; height: 15px; accent-color: var(--ink); }

    /* Alerts */
    .alert {
        display: flex;
        gap: 0.6rem;
        padding: 0.7rem 0.9rem;
        font-size: 0.875rem;
        border-radius: var(--radius);
        border: 1px solid transparent;
    }
    .alert-success { color: var(--ok); background: var(--ok-bg); border-color: color-mix(in srgb, var(--ok) 20%, transparent); }
    .alert-error { color: var(--danger); background: var(--danger-bg); border-color: color-mix(in srgb, var(--danger) 20%, transparent); }
    .alert-info { color: var(--info); background: var(--info-bg); border-color: color-mix(in srgb, var(--info) 20%, transparent); }

    /* Cards & layout helpers */
    .card {
        background: var(--surface);
        border: 1px solid var(--line);
        border-radius: 10px;
    }
    .card-pad { padding: 1.25rem; }
    .card-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
        padding: 0.9rem 1.25rem;
        border-bottom: 1px solid var(--line);
    }

    .row { display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap; }
    .stack > * + * { margin-top: 1rem; }

    /* Badges */
    .badge {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        height: 22px;
        padding: 0 0.55rem;
        font-size: 0.72rem;
        font-weight: 500;
        border-radius: 999px;
        border: 1px solid var(--line);
        color: var(--ink-soft);
        background: var(--surface-2);
        white-space: nowrap;
    }
    .badge::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
    .badge-pending { color: var(--ink-soft); }
    .badge-scanning { color: var(--info); background: var(--info-bg); border-color: transparent; }
    .badge-completed { color: var(--ok); background: var(--ok-bg); border-color: transparent; }
    .badge-failed { color: var(--danger); background: var(--danger-bg); border-color: transparent; }

    .sev {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        font-family: var(--mono);
        font-size: 0.75rem;
        color: var(--ink-soft);
        white-space: nowrap;
    }
    .sev::before { content: ''; width: 7px; height: 7px; border-radius: 2px; background: var(--sev-color, var(--line-strong)); }
    .sev-low { --sev-color: var(--sev-low); }
    .sev-medium { --sev-color: var(--sev-medium); }
    .sev-high { --sev-color: var(--sev-high); }
    .sev-critical { --sev-color: var(--sev-critical); color: var(--sev-critical); }

    /* Tables */
    .table-wrap { overflow-x: auto; }
    .table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
    .table th {
        text-align: left;
        font-size: 0.72rem;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--ink-faint);
        padding: 0.65rem 1.25rem;
        border-bottom: 1px solid var(--line);
        white-space: nowrap;
    }
    .table td { padding: 0.8rem 1.25rem; border-bottom: 1px solid var(--line); vertical-align: middle; }
    .table tr:last-child td { border-bottom: 0; }
    .table tbody tr:hover { background: var(--surface-2); }
    .table .num { text-align: right; }

    /* Empty state */
    .empty {
        padding: 3rem 1.5rem;
        text-align: center;
    }
    .empty h2 { margin-bottom: 0.35rem; }
    .empty p { color: var(--ink-soft); font-size: 0.9rem; max-width: 380px; margin: 0 auto 1.25rem; }

    /* Pagination */
    .pager { display: flex; justify-content: space-between; align-items: center; gap: 1rem; padding: 0.8rem 1.25rem; border-top: 1px solid var(--line); font-size: 0.825rem; }

    /* Scan log (preview) */
    .log {
        border: 1px solid #292524;
        border-radius: 10px;
        background: #141211;
        color: #e7e5e4;
        font-family: var(--mono);
        font-size: 0.78rem;
        overflow: hidden;
    }
    .log-bar { display: flex; justify-content: space-between; padding: 0.7rem 1rem; border-bottom: 1px solid #292524; color: #78716c; }
    .log-row { display: grid; grid-template-columns: 3.2rem minmax(0, 1fr) 2.6rem 4.4rem; gap: 0.75rem; align-items: center; padding: 0.55rem 1rem; border-bottom: 1px solid #1c1917; }
    .log-row:last-of-type { border-bottom: 0; }
    .log-row .m { color: #a8a29e; }
    .log-row .p { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .log-row .s { color: #78716c; text-align: right; }
    .log-row .r { justify-self: end; display: inline-flex; align-items: center; gap: 0.4rem; }
    .log-row .r::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
    .r-high { color: #f87171; }
    .r-med { color: #fbbf24; }
    .r-low { color: #78716c; }
    .r-ok { color: #4ade80; }
    .log-foot { display: flex; gap: 1.5rem; padding: 0.8rem 1rem; border-top: 1px solid #292524; color: #78716c; }
    .log-foot b { color: #e7e5e4; font-weight: 500; }
    .dark .log { background: #0c0a09; }
</style>
