@php
    $pdf = $pdf ?? false;
    $blockTitles = [
        'shared_intro' => 'Función y posición', 'function' => 'Función y posición',
        'sign' => 'Qué necesita este signo', 'house' => 'La casa y el territorio de experiencia',
        'ruler' => 'El regente y su posición', 'integration' => 'Integración de las piezas',
        'harmony' => 'Expresión armónica', 'deficit' => 'Expresión des-armónica por defecto',
        'excess' => 'Expresión des-armónica por exceso', 'closing' => 'Armonización e integración final',
    ];
@endphp

<style>
    .report-document { width: min(100%, 210mm); margin: -2rem auto; background: #fff; color: #202020; font-family: Aptos, 'Segoe UI', sans-serif; }
    .report-page { width: 100%; min-height: 297mm; padding: 20mm 18mm; border-bottom: 1px solid #eee; }
    .report-cover { min-height: 297mm; display: flex; flex-direction: column; justify-content: space-between; text-align: center; background: #fffdf9; }
    .report-brand { letter-spacing: .18em; font: 700 .75rem Aptos, 'Segoe UI', sans-serif; color: #6d5a48; }
    .report-cover h1 { margin: 5rem 0 1rem; font: 400 3.2rem/1.1 Aptos, 'Segoe UI', sans-serif; letter-spacing: 0; color: #1d1d1d; }
    .report-cover h2 { margin: 0; font: 400 1.35rem/1.5 Aptos, 'Segoe UI', sans-serif; color: #695c52; }
    .report-cover-name { margin-top: 3rem; font-size: 1.5rem; }
    .report-cover-meta { color: #6f665f; font: .95rem/1.8 Aptos, 'Segoe UI', sans-serif; }
    .report-kicker { display: inline-block; padding: .55rem 1.1rem; border: 1px solid #cbbba9; color: #695545; font: 700 .72rem Aptos, 'Segoe UI', sans-serif; letter-spacing: .12em; text-transform: uppercase; }
    .report-page h2 { margin: 0 0 2rem; font: 400 2rem/1.2 Aptos, 'Segoe UI', sans-serif; }
    .report-page h3 { margin: 2.5rem 0 1rem; font: 700 1.2rem/1.3 Aptos, 'Segoe UI', sans-serif; }
    .report-page p { max-width: 72ch; margin: 0 auto 1.25rem; font-size: 1rem; line-height: 1.75; }
    .report-page ul, .report-page ol { max-width: 68ch; margin: 1rem auto 1.5rem; padding-left: 1.5rem; line-height: 1.7; }
    .report-page ul { list-style: disc; }
    .report-page ol { list-style: decimal; }
    .report-index-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    .report-index-item { display: grid; grid-template-columns: 3rem 1fr; gap: 1rem; padding: 1.1rem; border-bottom: 1px solid #ded7cf; }
    .report-index-number { font: 700 1.5rem Aptos, 'Segoe UI', sans-serif; color: #9d7659; }
    .report-index-item strong { display: block; font-size: 1.05rem; }
    .report-index-item span { color: #766b64; font: .88rem Aptos, 'Segoe UI', sans-serif; }
    .report-gray { background: transparent; padding: 0; margin: 1.5rem 0; }
    .report-gray p { max-width: min(601px, calc(100% - 2rem)); font-family: Aptos, 'Segoe UI', sans-serif; font-size: .95rem; }
    .report-arrow-title { display: inline-block; padding: .65rem 0; background: transparent; font: 700 1rem Aptos, 'Segoe UI', sans-serif; }
    .report-door-intro-title { margin-bottom: 2rem; }
    .report-door { page-break-before: always; }
    .report-door-heading { border-bottom: 3px solid #e7cbb5; padding-bottom: 1rem; margin-bottom: 2rem; }
    .report-door-heading h2 { margin-bottom: .5rem; }
    .report-door-subtitle { color: #775c4d; font-style: italic; font-size: 1.15rem; }
    .report-block { margin: 2.5rem 0; }
    .report-block-title { display: inline-block; min-width: 52%; padding: .7rem 1.4rem; background: #f8dfcc; box-shadow: 4px 4px 0 rgba(122, 86, 61, .12); font: 700 1.1rem Aptos, 'Segoe UI', sans-serif; }
    .report-block-body { padding-top: 1.3rem; }
    .report-table { width: 100%; border-collapse: collapse; margin: 1.5rem 0 2rem; font: .9rem Aptos, 'Segoe UI', sans-serif; }
    .report-table th { background: #f0e9df; text-align: left; }
    .report-table th, .report-table td { padding: .8rem; border: 1px solid #d7d1ca; vertical-align: top; }
    .report-doors-table { table-layout: fixed; }
    .report-doors-table th:nth-child(1), .report-doors-table td:nth-child(1) { width: 15%; }
    .report-doors-table th:nth-child(2), .report-doors-table td:nth-child(2) { width: 40%; }
    .report-doors-table th:nth-child(3), .report-doors-table td:nth-child(3) { width: 45%; }
    .report-doors-table th { background: #f0e9df; font-weight: 700; }
    .report-note { border-left: 4px solid #b58b67; padding: 1rem 1.2rem; margin: 1.5rem 0; background: #f7f4f0; font: .9rem/1.6 Aptos, 'Segoe UI', sans-serif; }
    .report-document { padding-bottom: 5.5rem; }
    .report-pdf-only { display: none; }
    @if (!$pdf)
    .report-action-bar { position: fixed !important; z-index: 9999; left: 0; right: 0; bottom: 0; display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .65rem; padding: .7rem max(.7rem, calc((100vw - 920px) / 2)); border-top: 1px solid #d8cabc; background: rgba(255,253,249,.98); box-shadow: 0 -10px 28px rgba(68, 48, 33, .18); }
    .report-action { display: inline-flex; align-items: center; justify-content: center; height: 2.35rem; margin: 0; border: 1px solid #b58b67; border-radius: 5px; background: #fffaf5; color: #674b39; text-decoration: none; font: 500 .8rem Arial, sans-serif; font-weight: 500 !important; }
    .report-action-primary { background: #795c48; color: #fff; }
    @endif
    @if ($pdf)
    .report-pdf-only { display: block; }
    .report-pdf-header { position: fixed; top: -10mm; left: 0; right: 0; padding-bottom: 3mm; border-bottom: .4pt solid #d8cabc; color: #8a7768; font: 7.5pt Aptos, 'Segoe UI', sans-serif; letter-spacing: .08em; text-align: center; }
    .report-pdf-footer { position: fixed; right: 0; bottom: -10mm; color: #8a7768; font: 7.5pt Aptos, 'Segoe UI', sans-serif; }
    .report-pdf-footer::after { content: 'Página ' counter(page); }
    .report-pdf-indicator { display: block; margin: 1.2rem 0 .65rem; color: #795c48; font: 700 .82rem Aptos, 'Segoe UI', sans-serif; letter-spacing: .08em; }
    .report-pdf-indicator::before { content: '➜ '; color: #b58b67; }
    .report-arrow-title { display: inline-block; margin: 1.2rem 0 .65rem; color: #795c48; font: 700 .82rem Aptos, 'Segoe UI', sans-serif; letter-spacing: .08em; }
    .report-door-intro-title { margin-bottom: 2rem; }
    .report-arrow-title::before { content: '➜ '; color: #b58b67; }

    @media screen and (max-width: 700px) {
        .report-index-grid { grid-template-columns: 1fr; }
        .report-page { padding-left: 10mm; padding-right: 10mm; }
    }
    @page { size: A4 portrait; margin: 20mm 18mm; }
    html, body { margin: 0; padding: 0; background: #fff; }
    .page-shell, .card { max-width: none; margin: 0; padding: 0; border: 0; border-radius: 0; background: #fff; box-shadow: none; }
    .report-document { margin: 0; padding: 0; }
    .report-page { min-height: 0; height: auto; padding: 0; border: 0; }
    /* dompdf no soporta flexbox/grid: se reemplazan por posicionamiento de bloque compatible con A4 */
    .report-cover { display: block; position: relative; min-height: 250mm; height: 250mm; padding: 18mm 10mm; box-sizing: border-box; page-break-after: always; }
    .report-cover-meta { position: absolute; left: 10mm; right: 10mm; bottom: 18mm; margin-top: 0; }
    .report-index-grid { display: block; }
    .report-index-item { display: inline-block; width: 47%; margin: 0 1.5% 1rem; vertical-align: top; }
    .report-door { page-break-before: always; }
    .report-door-heading { page-break-inside: avoid; }
    .report-page h2 { font-size: 21pt; } .report-page p { max-width: 72ch; font-size: 10.5pt; line-height: 1.48; }
    .report-gray p { font-size: 9.5pt; line-height: 1.42; } .report-block { margin: 18pt 0; page-break-inside: avoid; }
    .report-block-title { padding: 6pt 10pt; font-size: 11pt; box-shadow: none; } .report-table { font-size: 8.5pt; }
    .report-table th, .report-table td { padding: 5pt; } .report-note { display: none; }
    @endif
</style>

<div class="report-document">
    @if ($pdf)
        <div class="report-pdf-header">SARANA VEDA · {{ strtoupper($report['name']) }}</div>
        <div class="report-pdf-footer" aria-hidden="true"></div>
    @endif
    <section class="report-page report-cover"><div><div class="report-brand">ASTROLOGÍA SARANA VEDA</div><div style="margin-top: 3rem;"><span class="report-kicker">Dossier personal</span></div><h1>Carta natal de<br>{{ $report['name'] }}</h1><h2>Primer informe de la fase 1<br>Sol · Luna · Ascendente · Descendente</h2><div class="report-cover-name">Identidad y voluntad<br>Necesidades emocionales<br>Ritmo propio y vínculos</div></div><div class="report-cover-meta">{{ $report['technical']['birth_date'] }} · {{ $report['technical']['birth_time'] }} · {{ $report['technical']['place'] }}<br>Zodiaco {{ $report['technical']['zodiac'] }} · Casas {{ $report['technical']['houses'] }}</div></section>
    <section class="report-page"><h2>Recorrido del dossier</h2><p>Este informe puede recorrerse en orden o consultarse por bloques. Cada apartado propone una pregunta y una forma de observarla en la experiencia cotidiana.</p><div class="report-index-grid">@foreach ($report['index'] as $item)<div class="report-index-item"><div class="report-index-number">{{ $item['number'] }}</div><div><strong>{{ $item['title'] }}</strong><span>{{ $item['summary'] }}</span></div></div>@endforeach</div></section>
    <section class="report-page"><h2>Tu primera lectura</h2><div class="report-gray">@foreach ($report['shared']['intro'] as $paragraph)<p>{!! $paragraph !!}</p>@endforeach</div><div class="report-arrow-title">LAS CUATRO PUERTAS</div><div class="report-gray">@foreach ($report['shared']['states'] as $paragraph)<p>{!! $paragraph !!}</p>@endforeach</div></section>
    <section class="report-page"><div class="report-arrow-title report-door-intro-title">UNA BREVE INTRODUCCIÓN A TUS CUATRO PUERTAS</div>@foreach ($report['door_introduction'] as $paragraph)<p>{{ $paragraph }}</p>@endforeach<table class="report-table report-doors-table"><tr><th>Puerta</th><th>Posición</th><th>Pregunta</th></tr>@foreach ($report['doors'] as $door)<tr><td>{{ str_replace(['Primera puerta el ', 'Segunda puerta la ', 'Tercera puerta el ', 'Cuarta puerta el '], '', $door['title']) }}</td><td>{{ $door['position'] }}</td><td>{{ $door['question'] }}</td></tr>@endforeach</table><div class="report-arrow-title">ESTADOS DE CADA PUERTA: Cómo reconocer armonía, defecto y exceso</div><div class="report-gray">@foreach ($report['shared']['states'] as $paragraph)<p>{!! $paragraph !!}</p>@endforeach</div></section>
    <section class="report-page"><div class="report-arrow-title">CONCLUSIONES IMPORTANTES PARA TU LECTURA</div><div class="report-gray">@foreach ($report['shared']['conclusions'] as $paragraph)<p>{!! $paragraph !!}</p>@endforeach</div></section>
    @foreach ($report['doors'] as $door)<section class="report-page report-door"><div class="report-door-heading"><h2>{{ $door['title'] }}</h2><div class="report-door-subtitle">{{ $door['subtitle'] }}</div></div>@foreach ($door['blocks'] as $block => $paragraphs)@if ($block !== 'shared_intro')<div class="report-block"><div class="report-block-title">{{ $blockTitles[$block] ?? ucfirst(str_replace('_', ' ', $block)) }}</div><div class="report-block-body">@foreach ((array) $paragraphs as $paragraph)@php($trimmedParagraph = trim($paragraph))@if (str_starts_with($trimmedParagraph, '<ol') || str_starts_with($trimmedParagraph, '<ul')){!! $trimmedParagraph !!}@else<p>{!! $paragraph !!}</p>@endif @endforeach</div></div>@endif @endforeach</section>@endforeach
    <section class="report-page"><h2>Cierre de tu primera lectura</h2>@foreach ($report['closing']['paragraphs'] as $paragraph)<p>{{ $paragraph }}</p>@endforeach<h3>Preguntas de autoobservación</h3><ul>@foreach ($report['closing']['questions'] as $question)<li>{{ $question }}</li>@endforeach</ul><div class="report-note"><strong>Frase de integración</strong><br>{{ $report['closing']['phrase'] }}</div></section>
    <section class="report-page"><h2>Datos y criterios de cálculo</h2><p>Nombre: {{ $report['name'] }}. Nacimiento facilitado: {{ $report['technical']['birth_date'] }}, a las {{ $report['technical']['birth_time'] }}, en {{ $report['technical']['place'] }}. La hora se interpreta como hora civil local.</p><p>{{ $report['technical']['legal_time_note'] }}</p><p>Coordenadas de referencia: {{ $report['technical']['coordinates'] }}. Zodiaco tropical, posiciones geocéntricas y casas Placidus. Motor de cálculo: {{ $report['technical']['engine'] }}. Día juliano: {{ $report['technical']['julian_day'] }}.</p><p>{{ $report['technical']['house_assignment_note'] }}</p><h3>Posiciones</h3><table class="report-table"><tr><th>Posición</th><th>Signo y grado</th><th>Casa</th></tr>@foreach ($report['technical']['positions'] as $position)<tr><td>{{ $position['name'] }}</td><td>{{ $position['position'] }}</td><td>{{ $position['house'] }}</td></tr>@endforeach</table><p>{{ $report['technical']['venus_cusp_note'] }}</p><p>{{ $report['technical']['regencies_note'] }}</p></section>
</div>
