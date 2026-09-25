@extends('layouts.app')

@section('title', 'Revisar informe')
@section('content')
    @php($labels = ['sol' => 'Sol', 'luna' => 'Luna', 'ascendente' => 'Ascendente', 'descendente' => 'Descendente'])
    <h1>Este apartado necesita regenerarse</h1>
    <p>El contenido guardado de {{ implode(', ', array_map(fn ($door) => $labels[$door], $doors)) }} utiliza un formato antiguo o tiene una estructura incompleta o repetida.</p>
    <p>Regenera los apartados indicados para abrir el informe y crear el PDF. Se generará contenido nuevo; cada apartado se guardará cuando esté completo y validado. Los demás apartados se conservarán.</p>
    <button type="button" id="repair-door" data-report-job-url="{{ route('reports.jobs.store', $chart) }}" data-doors='@json($doors)' >{{ count($doors) === 1 ? 'Regenerar esta puerta' : 'Regenerar los apartados pendientes' }}</button>
    <p id="repair-status" role="status" aria-live="polite"></p>
    <a href="{{ route('charts.show', $chart) }}">Volver a la carta</a>

@endsection
