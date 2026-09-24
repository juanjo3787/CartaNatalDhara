@extends('layouts.app')

@section('title', 'Revisar informe')
@section('content')
    @php
        $stageUrls = collect(\App\Services\Doors\AbstractDoorPipeline::STAGES)->mapWithKeys(fn ($stage) => [$stage => route('charts.report.ai.stage', [$chart, $door, $stage])])->all();
    @endphp
    <h1>Este apartado necesita regenerarse</h1>
    <p>El contenido guardado de {{ ['sol' => 'Sol', 'luna' => 'Luna', 'ascendente' => 'Ascendente', 'descendente' => 'Descendente'][$door] }} tiene una estructura incompleta o repetida. No se ha eliminado ni modificado.</p>
    <p>Regenera esta puerta para poder abrir el informe y crear un PDF correcto.</p>
    <button type="button" id="repair-door">Regenerar esta puerta</button>
    <p id="repair-status" role="status" aria-live="polite"></p>
    <a href="{{ route('charts.show', $chart) }}">Volver a la carta</a>
    <script>
        document.getElementById('repair-door').addEventListener('click', async function () {
            const status = document.getElementById('repair-status');
            const stages = @json(\App\Services\Doors\AbstractDoorPipeline::STAGES);
            const urls = @json($stageUrls);
            this.disabled = true;
            try {
                for (let i = 0; i < stages.length; i++) {
                    status.textContent = `Generando esta puerta: ${i + 1} de ${stages.length} pasos.`;
                    const response = await fetch(urls[stages[i]], {
                        method: 'POST',
                        headers: {'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded'},
                        body: new URLSearchParams({_token: @json(csrf_token())}),
                    });
                    const result = await response.json().catch(() => ({}));
                    if (!response.ok) throw new Error(result.message || `No se pudo completar la generación. HTTP ${response.status}.`);
                }
                window.location.assign(@json(route('charts.report', $chart)));
            } catch (error) {
                status.textContent = error.message;
                this.disabled = false;
            }
        });
    </script>
@endsection
