<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carta Natal - @yield('title', 'Fase 1')</title>
    @if (empty($pdf))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    <style>
        :root {
            --bg: #fffdf9;
            --panel: rgba(255,255,255,0.96);
            --panel-strong: #ffffff;
            --ink: #202020;
            --muted: #766b64;
            --line: #d8cabc;
            --primary: #795c48;
            --primary-dark: #5e4535;
            --gold: #b58b67;
            --success: #5f8066;
        }

        html { font-size: 85%; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            color: var(--ink);
            background: #fffdf9;
            padding: 2rem 1rem;
        }

        .page-shell {
            max-width: 980px;
            margin: 0 auto;
            padding-bottom: 5.5rem;
        }

        .card {
            background: var(--panel);
            backdrop-filter: blur(10px);
            border: 1px solid var(--line);
            border-radius: 22px;
            box-shadow: 0 18px 45px rgba(68, 48, 33, 0.08);
            padding: 2rem;
        }

        h1 {
            margin: 0 0 1.25rem;
            font-size: clamp(1.8rem, 2vw, 2.5rem);
            letter-spacing: -0.04em;
        }

        h2 {
            margin: 1.5rem 0 0.75rem;
            font-size: 1.2rem;
        }

        label {
            display: block;
            margin-top: 1rem;
            font-weight: 500;
            color: var(--ink);
        }

        input, select, button {
            font: inherit;
        }

        input, select, textarea {
            width: 100%;
            min-height: 2.35rem;
            padding: 0.55rem 0.9rem;
            margin-top: 0.4rem;
            border: 1px solid #d7d1ca;
            border-radius: 12px;
            background: rgba(255,255,255,0.92);
            color: var(--ink);
        }

        textarea { line-height: 1.45; }

        select {
            padding: .25rem .9rem;
            line-height: 1.2;
            appearance: none;
            cursor: pointer;
            padding-right: 2.5rem;
            background-image:
                linear-gradient(45deg, transparent 50%, #795c48 50%),
                linear-gradient(135deg, #795c48 50%, transparent 50%);
            background-position:
                calc(100% - 1.15rem) 50%,
                calc(100% - .82rem) 50%;
            background-size: .35rem .35rem, .35rem .35rem;
            background-repeat: no-repeat;
        }

        input:focus, select:focus {
            outline: 2px solid rgba(121, 92, 72, 0.18);
            border-color: var(--primary);
        }

        .row {
            display: flex;
            gap: 1rem;
            align-items: flex-end;
        }

        .row > div { flex: 1; }

        button {
            margin-top: 1.25rem;
            padding: 0.8rem 1.2rem;
            border: none;
            border-radius: 12px;
            background: var(--primary);
            color: white;
            cursor: pointer;
            font-weight: 700;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
            box-shadow: 0 8px 20px rgba(68, 48, 33, 0.2);
        }

        button:hover {
            transform: translateY(-1px);
        }

        .error { color: #b91c1c; font-size: 0.875rem; }

        .p-message {
            width: 100%;
            margin: 0 0 1rem;
            border: 1px solid;
            border-radius: 6px;
            font-size: .95rem;
        }

        .p-message-wrapper {
            display: flex;
            align-items: flex-start;
            gap: .7rem;
            padding: .85rem 1rem;
        }

        .p-message-icon {
            display: inline-flex;
            flex: 0 0 1.25rem;
            align-items: center;
            justify-content: center;
            width: 1.25rem;
            height: 1.25rem;
            border-radius: 50%;
            font-weight: 800;
            line-height: 1;
        }

        .p-message-error {
            border-color: #f5b5b5;
            background: #fff2f2;
            color: #b42318;
        }

        .p-message-error .p-message-icon {
            background: #b42318;
            color: #fff;
        }

        .p-message-success {
            border-color: #a7d7b1;
            background: #f0fdf4;
            color: #166534;
        }

        .p-message-success .p-message-icon {
            background: #16803c;
            color: #fff;
        }

        .p-message-list {
            margin: .45rem 0 0;
            padding-left: 1.2rem;
        }

        .p-message-list li + li { margin-top: .25rem; }

        .field-error {
            display: block;
            margin-top: .35rem;
            color: #b42318;
            font-size: .82rem;
        }

        .p-toast {
            position: fixed;
            top: 1rem;
            right: 1rem;
            z-index: 10001;
            display: grid;
            gap: .65rem;
            width: min(24rem, calc(100vw - 2rem));
        }

        .p-toast-message {
            display: flex;
            align-items: flex-start;
            gap: .7rem;
            border: 1px solid;
            border-radius: 6px;
            padding: .85rem 1rem;
            box-shadow: 0 8px 24px rgba(68, 48, 33, .16);
            animation: p-toast-in .2s ease-out;
        }

        .p-toast-message-success {
            border-color: #a7d7b1;
            background: #f0fdf4;
            color: #166534;
        }

        .p-toast-message-error {
            border-color: #f5b5b5;
            background: #fff2f2;
            color: #b42318;
        }

        .p-toast-icon {
            display: inline-flex;
            flex: 0 0 1.25rem;
            align-items: center;
            justify-content: center;
            width: 1.25rem;
            height: 1.25rem;
            border-radius: 50%;
            color: #fff;
            font-weight: 800;
        }

        .p-toast-message-success .p-toast-icon { background: #16803c; }
        .p-toast-message-error .p-toast-icon { background: #b42318; }

        .p-toast-detail {
            display: grid;
            gap: .2rem;
            flex: 1;
            line-height: 1.35;
        }

        .p-toast-close {
            width: 1.5rem;
            min-height: 1.5rem;
            margin: -.15rem -.35rem 0 0;
            padding: 0;
            border: 0;
            background: transparent;
            color: currentColor;
            box-shadow: none;
            font-size: 1.25rem;
            line-height: 1;
        }

        .p-toast-close:hover { transform: none; opacity: .7; }

        @keyframes p-toast-in {
            from { opacity: 0; transform: translateY(-.35rem); }
            to { opacity: 1; transform: translateY(0); }
        }

        input:has(+ .field-error), select:has(+ .field-error), textarea:has(+ .field-error) {
            border-color: #dc2626;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
            background: rgba(255,255,255,0.5);
            border-radius: 12px;
            overflow: hidden;
        }

        th, td {
            text-align: left;
            padding: 0.8rem 0.9rem;
            border-bottom: 1px solid var(--line);
        }

        th {
            background: #f0e9df;
            color: var(--ink);
            font-weight: 700;
        }

        tbody tr:hover {
            background: #fffaf5;
        }

        .note {
            background: #f7f4f0;
            border: 1px solid var(--line);
            padding: 1rem 1.1rem;
            border-radius: 16px;
            font-size: 0.95rem;
            margin-top: 1rem;
            color: var(--muted);
            line-height: 1.6;
        }

        a { color: var(--primary); text-decoration: none; }
        a:hover { text-decoration: underline; }

        .toolbar {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            align-items: center;
            margin-bottom: 1rem;
        }

        .badge {
            display: inline-block;
            background: #f8dfcc;
            border: 1px solid #cbbba9;
            color: #674b39;
            border-radius: 999px;
            padding: 0.35rem 0.7rem;
            font-size: 0.8rem;
            font-weight: 700;
        }

        .app-loading {
            position: fixed;
            inset: 0;
            z-index: 10000;
            display: none;
            align-items: center;
            justify-content: center;
            background: rgba(32, 26, 52, 0.28);
            backdrop-filter: blur(3px);
        }

        .app-loading.is-visible { display: flex; }

        .app-loading-panel {
            display: flex;
            align-items: center;
            gap: .75rem;
            padding: 1rem 1.25rem;
            border: 1px solid rgba(255,255,255,.8);
            border-radius: 12px;
            background: rgba(255,253,249,.98);
            color: var(--ink);
            font-weight: 700;
            box-shadow: 0 12px 35px rgba(68, 48, 33, .2);
        }

        .app-loading-spinner {
            width: 1.25rem;
            height: 1.25rem;
            border: 3px solid #e7d8ca;
            border-top-color: #795c48;
            border-radius: 50%;
            animation: app-loading-spin .75s linear infinite;
        }

        @keyframes app-loading-spin { to { transform: rotate(360deg); } }

        .app-action-bar {
            position: fixed;
            z-index: 9998;
            left: 0;
            right: 0;
            bottom: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: .65rem;
            padding: .7rem max(.7rem, calc((100vw - 920px) / 2));
            border-top: 1px solid var(--line);
            background: rgba(255,253,249,.98);
            box-shadow: 0 -10px 28px rgba(68, 48, 33, .18);
        }

        .app-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 1 1 0;
            width: 100%;
            max-width: 18rem;
            height: 2.35rem;
            margin: 0;
            padding: .55rem .9rem;
            border: 1px solid #b58b67;
            border-radius: 5px;
            background: #fff;
            color: #674b39;
            text-decoration: none;
            font-weight: 700;
            cursor: pointer;
        }

        .app-action-primary { background: #795c48; color: #fff; border-color: #795c48; }
        .app-action:hover { text-decoration: none; filter: brightness(.97); }

        .file-download-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2.35rem;
            height: 2.35rem;
            padding: 0;
            border: 1px solid #b58b67;
            border-radius: 50%;
            background: #fffaf5;
            color: #795c48;
            cursor: pointer;
        }

        .file-download-action:hover { background: #f8dfcc; }
        .file-download-action svg { width: 1.15rem; height: 1.15rem; }

        .delete-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2.35rem;
            height: 2.35rem;
            margin: 0;
            padding: 0;
            border: 1px solid #b58b67;
            border-radius: 50%;
            background: #fffaf5;
            color: #795c48;
            cursor: pointer;
            box-shadow: none;
        }

        .delete-action:hover { background: #f8dfcc; }
        .delete-action svg { width: 1.15rem; height: 1.15rem; }

        .form-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            height: 2.35rem;
            margin: 0;
            padding: .55rem .9rem;
            border: 1px solid #795c48;
            border-radius: 5px;
            background: #795c48;
            color: #fff;
            font-weight: 500 !important;
            box-shadow: none;
        }

        .form-action:hover { background: #5e4535; }

        .address-action .form-action { transform: translateY(-.2rem); }

        button,
        .app-action,
        .report-action {
            font-weight: 500 !important;
        }

        @media (max-width: 700px) {
            .app-action-bar { gap: .4rem; padding: .45rem; }
            .app-action { min-width: 0; flex: 1; padding: .5rem .35rem; font-size: .72rem; }
        }

        @media print { .app-action-bar { display: none; } }

        body.is-loading { cursor: wait; }
        body.is-loading a, body.is-loading button { pointer-events: none; }
    </style>
</head>
<body>
    <div class="page-shell">
        <div class="card">
            @yield('content')
        </div>
    </div>
    @yield('floating_actions')

    <div class="app-loading" data-app-loading aria-live="polite" aria-busy="false">
        <div class="app-loading-panel">
            <span class="app-loading-spinner" aria-hidden="true"></span>
            <span>Procesando...</span>
        </div>
    </div>

    @include('components.toast')

    @if (empty($pdf))
        <script>
            (() => {
                document.querySelectorAll('[data-toast]').forEach((toast) => {
                    const close = () => toast.remove();
                    toast.querySelector('[data-toast-close]')?.addEventListener('click', close);
                    window.setTimeout(close, 5000);
                });

                const overlay = document.querySelector('[data-app-loading]');

                if (!overlay) return;

                const showLoading = () => {
                    overlay.classList.add('is-visible');
                    overlay.setAttribute('aria-busy', 'true');
                    document.body.classList.add('is-loading');
                };

                document.addEventListener('submit', (event) => {
                    if (!event.defaultPrevented) showLoading();
                }, true);

                document.addEventListener('click', (event) => {
                    const action = event.target.closest('a[data-loading], a[href], button[type="submit"]');

                    if (!action || event.defaultPrevented) return;
                    if (action.tagName === 'A') {
                        const href = action.getAttribute('href') || '';
                        if (!href || href.startsWith('#') || href.startsWith('javascript:') || action.target === '_blank') return;
                    }

                    showLoading();
                    if (action.tagName === 'A' && (action.getAttribute('href') || '').includes('/download')) {
                        window.setTimeout(() => {
                            overlay.classList.remove('is-visible');
                            overlay.setAttribute('aria-busy', 'false');
                            document.body.classList.remove('is-loading');
                        }, 1500);
                    }
                }, true);

                window.addEventListener('pageshow', () => {
                    overlay.classList.remove('is-visible');
                    overlay.setAttribute('aria-busy', 'false');
                    document.body.classList.remove('is-loading');
                });
            })();
        </script>
    @endif
</body>
</html>
