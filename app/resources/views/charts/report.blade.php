@extends('layouts.app')

@section('title', 'Informe Fase 1 - ' . $report['name'])

@section('content')
<div class="report-regenerate-error" data-report-regenerate-error hidden role="alert"></div>
    @include('reports.phase-one.template', ['report' => $report, 'chart' => $chart, 'pdf' => $pdf ?? false])
@endsection

@if (empty($pdf))
@section('floating_actions')
    <style>
        .report-regenerate-error { margin: 1rem auto; max-width: 1100px; padding: .9rem 1rem; border-left: 4px solid #9f2d2d; background: #fff0f0; color: #7e2020; font: .9rem/1.5 Aptos, 'Segoe UI', sans-serif; }
        .report-action-bar { position: fixed !important; z-index: 9999; left: 0; right: 0; bottom: 0; display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: .65rem; padding: .7rem max(.7rem, calc((100vw - 1100px) / 2)); border-top: 1px solid #d8cabc; background: rgba(255,253,249,.98); box-shadow: 0 -10px 28px rgba(68,48,33,.18); }
        .report-action { display: inline-flex; align-items: center; justify-content: center; height: 2.35rem; margin: 0; border: 1px solid #b58b67; border-radius: 5px; background: #fffaf5; color: #674b39; text-decoration: none; font: 500 .8rem Aptos, 'Segoe UI', sans-serif; font-weight: 500 !important; }
        .report-action-primary { background: #795c48; color: #fff; }
        .report-action-validate { background: #5f8066; color: #fff; border-color: #5f8066; }
        @media (max-width:700px) { .report-action-bar { padding: .45rem; } .report-action { font-size: .72rem; } }
    </style>
        <nav class="report-action-bar" aria-label="Acciones del informe">
            <form id="regenerate-report-form" method="POST" action="{{ route('charts.report.regenerate', $chart) }}" data-confirm-message="¿Regenerar el informe con IA y actualizar el PDF?">@csrf<button class="report-action report-action-primary" type="submit">Regenerar informe</button></form>
        <form method="POST" action="{{ route('charts.report.validate', $chart) }}">@csrf<button class="report-action report-action-validate" type="submit">Validar y guardar PDF</button></form>
        <a class="report-action" href="{{ route('charts.report.download', $chart) }}">Descargar PDF</a>
        <a class="report-action report-action-primary" href="{{ route('charts.report.edit', $chart) }}">Editar informe</a>
        <a class="report-action" href="{{ route('charts.show', $chart) }}">Volver a la carta</a>
    </nav>
@endsection
@endif

@if (empty($pdf))
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('regenerate-report-form');
    const overlay = document.querySelector('[data-app-loading]');
    const progress = document.querySelector('[data-app-loading-progress]');
    const progressBar = document.querySelector('[data-app-loading-progress-bar]');
    const progressValue = document.querySelector('[data-app-loading-progress-value]');
    const loadingMessage = document.querySelector('[data-app-loading-message]');
    const errorPanel = document.querySelector('[data-report-regenerate-error]');
    const doors = ['sol', 'luna', 'ascendente', 'descendente'];

    form?.addEventListener('submit', async (event) => {
        if (event.defaultPrevented || form.dataset.confirmed !== 'true') return;

        event.preventDefault();
        const csrf = form.querySelector('input[name="_token"]')?.value;
        const setProgress = (value) => {
            progressBar.value = value;
            progressValue.textContent = `${value}%`;
        };

        overlay.classList.add('is-visible');
        overlay.setAttribute('aria-busy', 'true');
        loadingMessage.classList.remove('is-error');
        loadingMessage.textContent = 'Generando contenido con IA...';
        errorPanel.hidden = true;
        progress.classList.add('is-visible');
        progress.setAttribute('aria-hidden', 'false');
        setProgress(0);

        try {
            for (let index = 0; index < doors.length; index += 1) {
                const controller = new AbortController();
                const timeout = window.setTimeout(() => controller.abort(), doors[index] === 'sol' ? 1200000 : 90000);
                const response = await fetch(`{{ url('/charts/' . $chart->id . '/report/ai') }}/${doors[index]}`, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ _token: csrf }),
                    signal: controller.signal,
                });
                window.clearTimeout(timeout);
                const body = await response.text();
                let payload = null;
                try { payload = JSON.parse(body); } catch (_) {}
                if (!response.ok || body.includes('Error de IA:')) {
                    const htmlMessage = new DOMParser().parseFromString(body, 'text/html').querySelector('[data-toast], [role="alert"], .error')?.textContent?.trim();
                    const message = payload?.message || htmlMessage || `No se pudo generar ${doors[index]}. HTTP ${response.status}.`;
                    throw new Error(message.replace(/<[^>]+>/g, '').trim());
                }
                setProgress(Math.round(((index + 1) / doors.length) * 100));
                loadingMessage.textContent = `${doors[index]} completado. Preparando la siguiente puerta...`;
            }

            const pdfResponse = await fetch(form.action, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ _token: csrf, ai_ready: '1' }),
            });
            if (!pdfResponse.ok) throw new Error(`No se pudo actualizar el PDF. HTTP ${pdfResponse.status}.`);
            window.location.assign('{{ route('charts.report', $chart) }}');
        } catch (error) {
            overlay.classList.remove('is-visible');
            overlay.setAttribute('aria-busy', 'false');
            progress.classList.remove('is-visible');
            form.dataset.confirmed = 'false';
            const message = error.name === 'AbortError'
                ? 'La generación de IA superó el tiempo máximo de espera.'
                : (error.message || 'No se pudo regenerar el informe con IA.');
            errorPanel.textContent = `Error durante la regeneración con IA: ${message}`;
            errorPanel.hidden = false;
        }
    }, true);
});
</script>
@endif
