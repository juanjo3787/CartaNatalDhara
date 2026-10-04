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
    $snapshot = $chart->snapshot ?? [];
    $wheelPlanets = collect([
        'sun', 'moon', 'mercury', 'venus', 'mars', 'jupiter', 'saturn', 'uranus', 'neptune', 'pluto',
        'true_node', 'mean_apogee',
    ])->mapWithKeys(function (string $name) use ($snapshot): array {
        $point = $snapshot[$name] ?? null;
        if (! is_array($point) || ! isset($point['longitude'])) {
            return [];
        }

        $libraryName = match ($name) {
            'true_node' => 'rahu',
            'mean_apogee' => 'lilith',
            default => $name,
        };

        return [$libraryName => ['lon' => (float) $point['longitude']]];
    })->all();
    if (isset($wheelPlanets['rahu'])) {
        $wheelPlanets['ketu'] = ['lon' => fmod($wheelPlanets['rahu']['lon'] + 180, 360)];
    }
    $wheelHouses = collect($snapshot['houses'] ?? [])->sortKeys()->map(fn (array $house): array => [
        'lon' => (float) $house['longitude'],
    ])->values()->all();
    $houseSystemLabels = [
        'placidus' => 'Placidus',
        'koch' => 'Koch',
        'equal' => 'Casas iguales',
        'whole_sign' => 'Signo entero',
        'regiomontanus' => 'Regiomontano',
        'campanus' => 'Campanus',
        'porphyry' => 'Porfirio',
        'morinus' => 'Morinus',
        'topocentric' => 'Topocéntrico',
    ];
    $houseSystem = $chart->configuration['houses'] ?? 'placidus';
    $aspectMatrix = app(\App\Services\AspectMatrixBuilder::class)->build($snapshot);
@endphp

@section('content')
    <div class="toolbar">
        <span class="badge">Carta natal</span>
    </div>

    <h1>Carta de {{ $chart->person->alias }}</h1>
    <form method="POST" action="{{ route('reports.jobs.store', $chart) }}" data-report-job>
        @csrf
        <button type="submit">Generar informe en segundo plano</button>
    </form>

    <style>
        .wheel-controls { margin: 1.5rem 0; padding-bottom: 1rem; border-bottom: 1px solid var(--line); }
        .wheel-control-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: .75rem 1rem; align-items: end; }
        .wheel-control-row label { margin-top: .5rem; }
        .wheel-aspects { display: flex; flex-wrap: wrap; gap: .5rem 1rem; margin-top: .75rem; }
        .wheel-aspects label { display: inline-flex; align-items: center; gap: .35rem; margin: 0; }
        .wheel-aspects input { width: auto; min-height: 0; margin: 0; }
        .wheel-status { min-height: 1.4rem; margin-top: .5rem; color: var(--muted); }
        [hidden] { display: none !important; }
    </style>

    <div data-wheel-panel data-transit-url="{{ route('charts.transits', $chart) }}">
        <div class="wheel-controls">
            <div class="wheel-control-row">
                <div>
                    <label for="secondary-chart-mode">Segundo círculo</label>
                    <select id="secondary-chart-mode" data-secondary-mode>
                        <option value="none">Sin segundo círculo</option>
                        <option value="transits" selected>Tránsitos</option>
                        <option value="synastry">Sinastría</option>
                    </select>
                </div>
                <div data-transit-controls>
                    <label for="transit-date-time">Fecha y hora local</label>
                    <input id="transit-date-time" type="datetime-local" value="{{ now($chart->birthData->timezone_identifier)->format('Y-m-d\\TH:i') }}" data-transit-date-time>
                    <button type="button" data-calculate-transits>Calcular tránsitos</button>
                </div>
                <div data-synastry-controls hidden>
                    <label for="synastry-chart">Carta para comparar</label>
                    <select id="synastry-chart" data-synastry-chart>
                        <option value="">{{ $secondaryCharts->isEmpty() ? 'No hay otras cartas guardadas' : 'Selecciona una carta guardada' }}</option>
                        @foreach ($secondaryCharts as $secondaryChart)
                            <option value="{{ $secondaryChart['id'] }}" data-planets="{{ json_encode($secondaryChart['planets'], JSON_HEX_APOS | JSON_HEX_QUOT) }}">{{ $secondaryChart['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="aspect-orb">Orbe de aspectos (grados)</label>
                    <input id="aspect-orb" type="number" min="0" max="15" step="0.5" value="6" data-aspect-orb>
                </div>
            </div>
            <fieldset style="margin-top: .75rem;">
                <legend>Aspectos visibles</legend>
                <div class="wheel-aspects">
                    <label><input type="checkbox" value="conjunction" checked data-aspect-type> Conjunción</label>
                    <label><input type="checkbox" value="opposition" checked data-aspect-type> Oposición</label>
                    <label><input type="checkbox" value="trine" checked data-aspect-type> Trígono</label>
                    <label><input type="checkbox" value="square" checked data-aspect-type> Cuadratura</label>
                    <label><input type="checkbox" value="sextile" checked data-aspect-type> Sextil</label>
                </div>
            </fieldset>
            <p class="wheel-status" role="status" aria-live="polite" data-wheel-status></p>
        </div>

        <div
            class="nocturna-wheel-container"
            data-natal-wheel
            data-chart="{{ json_encode([
                'planets' => $wheelPlanets,
                'houses' => $wheelHouses,
                'ascendant' => (float) ($snapshot['ascendant']['longitude'] ?? 0),
                'midheaven' => (float) ($snapshot['midheaven']['longitude'] ?? 0),
                'latitude' => (float) $chart->birthData->place->latitude,
                'houseSystem' => $houseSystem,
            ], JSON_HEX_APOS | JSON_HEX_QUOT) }}"
            style="width: min(100%, 760px); aspect-ratio: 1; margin: 0 auto;"
        ></div>

        @include('components.aspect-matrix', $aspectMatrix + ['showPositions' => true])
    </div>

    <div class="note">
        Nacimiento local: {{ $chart->birthData->local_date->format('d/m/Y') }} {{ $chart->birthData->local_time }}
        ({{ $chart->birthData->timezone_identifier }}, offset {{ $chart->birthData->utc_offset }})<br>
        UTC utilizado para el cálculo: {{ $chart->birthData->utc_datetime }}<br>
        Lugar: {{ $chart->birthData->place->city }}, {{ $chart->birthData->place->country }}
        ({{ $chart->birthData->place->latitude }}, {{ $chart->birthData->place->longitude }})<br>
        Sistema: {{ $chart->configuration['zodiac'] }} / {{ $houseSystemLabels[$houseSystem] ?? $houseSystem }} / {{ $chart->engine_version }}
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
