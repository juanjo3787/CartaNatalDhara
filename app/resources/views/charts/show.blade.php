@extends('layouts.app')

@section('title', 'Carta #' . $chart->id)

@php
    $translateSign = function (string $sign): string {
        $map = [
            'aries' => 'Aries',
            'taurus' => 'Tauro',
            'gemini' => 'Géminis',
            'cancer' => 'Cáncer',
            'leo' => 'Leo',
            'virgo' => 'Virgo',
            'libra' => 'Libra',
            'scorpio' => 'Escorpio',
            'sagittarius' => 'Sagitario',
            'capricorn' => 'Capricornio',
            'aquarius' => 'Acuario',
            'pisces' => 'Piscis',
        ];

        return $map[strtolower(trim($sign))] ?? ucfirst(strtolower(trim($sign)));
    };

    $describe = fn (array $p) => sprintf('%s %d° %d\' %s"', $translateSign($p['sign'] ?? ''), $p['degrees'], $p['minutes'], $p['seconds']);
    $snapshot = $chart->snapshot;
    $wheelPlanets = collect([
        'sun', 'moon', 'mercury', 'venus', 'mars', 'jupiter', 'saturn', 'uranus', 'neptune', 'pluto',
        'true_node', 'mean_apogee',
    ])->mapWithKeys(function (string $name) use ($snapshot): array {
        $point = $snapshot[$name] ?? null;
        if (! $point || ! isset($point['longitude'])) {
            return [];
        }

        $libraryName = match ($name) {
            'true_node' => 'rahu',
            'mean_apogee' => 'lilith',
            default => $name,
        };

        return [$libraryName => ['lon' => (float) $point['longitude']]];
    })->all();
    $wheelHouses = collect($snapshot['houses'] ?? [])->sortKeys()->map(fn (array $house): array => [
        'lon' => (float) $house['longitude'],
    ])->values()->all();
@endphp

@section('content')
    <div class="toolbar">
        <span class="badge">Carta natal</span>
    </div>

    <h1>Carta de {{ $chart->person->alias }}</h1>

    <div style="margin: 1.5rem 0; display: flex; justify-content: center; background: rgba(255,255,255,0.45); border: 1px solid var(--line); border-radius: 18px; padding: 1rem;">
        <div
            class="nocturna-wheel-container"
            data-natal-wheel
            data-chart="{{ json_encode([
                'planets' => $wheelPlanets,
                'houses' => $wheelHouses,
                'ascendant' => (float) ($snapshot['ascendant']['longitude'] ?? 0),
                'midheaven' => (float) ($snapshot['midheaven']['longitude'] ?? 0),
                'latitude' => (float) $chart->birthData->place->latitude,
            ], JSON_HEX_APOS | JSON_HEX_QUOT) }}"
            style="width: min(100%, 760px); aspect-ratio: 1;"
        ></div>
    </div>

    <div class="note">
        Nacimiento local: {{ $chart->birthData->local_date->format('d/m/Y') }} {{ $chart->birthData->local_time }}
        ({{ $chart->birthData->timezone_identifier }}, offset {{ $chart->birthData->utc_offset }})<br>
        UTC utilizado para el cálculo: {{ $chart->birthData->utc_datetime }}<br>
        Lugar: {{ $chart->birthData->place->city }}, {{ $chart->birthData->place->country }}
        ({{ $chart->birthData->place->latitude }}, {{ $chart->birthData->place->longitude }})<br>
        Sistema: {{ $chart->configuration['zodiac'] }} / {{ $chart->configuration['houses'] }} / {{ $chart->engine_version }}
    </div>

    <table>
        <tr><th>Punto</th><th>Posición</th></tr>
        <tr><td>Sol</td><td>{{ $describe($chart->snapshot['sun']) }}</td></tr>
        <tr><td>Luna</td><td>{{ $describe($chart->snapshot['moon']) }}</td></tr>
        <tr><td>Ascendente</td><td>{{ $describe($chart->snapshot['ascendant']) }}</td></tr>
        <tr><td>Descendente</td><td>{{ $describe($chart->snapshot['descendant']) }}</td></tr>
        <tr><td>Medio Cielo (MC)</td><td>{{ $describe($chart->snapshot['midheaven']) }}</td></tr>
        <tr><td>Nodo Verdadero</td><td>{{ $describe($chart->snapshot['true_node']) }}</td></tr>
        <tr><td>Lilith (apogeo medio)</td><td>{{ $describe($chart->snapshot['mean_apogee']) }}</td></tr>
    </table>

    <h2>Casas</h2>
    <table>
        <tr><th>Casa</th><th>Posición</th></tr>
        @foreach ($chart->snapshot['houses'] as $number => $house)
            <tr><td>{{ $number }}</td><td>{{ $describe($house) }}</td></tr>
        @endforeach
    </table>

@endsection

@section('floating_actions')
    <nav class="app-action-bar" aria-label="Acciones de carta">
        <a class="app-action app-action-primary" href="{{ route('charts.report', $chart) }}">Informe Fase 1</a>
        <a class="app-action" href="{{ route('charts.create') }}">Nueva carta</a>
        <a class="app-action" href="{{ route('charts.index') }}">Ver cartas</a>
    </nav>
@endsection
