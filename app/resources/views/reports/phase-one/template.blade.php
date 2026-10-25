@php
    $pdf = $pdf ?? false;
    $reportSnapshot = $chart->snapshot ?? [];
    $coverAspectMatrix = app(\App\Services\AspectMatrixBuilder::class)->build($reportSnapshot);
    $coverWheelPlanets = collect([
        'sun', 'moon', 'mercury', 'venus', 'mars', 'jupiter', 'saturn', 'uranus', 'neptune', 'pluto',
        'true_node', 'mean_apogee',
    ])->mapWithKeys(function (string $name) use ($reportSnapshot): array {
        $point = $reportSnapshot[$name] ?? null;
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
    if (isset($coverWheelPlanets['rahu'])) {
        $coverWheelPlanets['ketu'] = ['lon' => fmod($coverWheelPlanets['rahu']['lon'] + 180, 360)];
    }
    $coverWheelData = [
        'planets' => $coverWheelPlanets,
        'houses' => collect($reportSnapshot['houses'] ?? [])->sortKeys()->map(fn (array $house): array => ['lon' => (float) $house['longitude']])->values()->all(),
        'ascendant' => (float) ($reportSnapshot['ascendant']['longitude'] ?? 0),
        'midheaven' => (float) ($reportSnapshot['midheaven']['longitude'] ?? 0),
        'latitude' => (float) $chart->birthData->place->latitude,
        'houseSystem' => $chart->configuration['houses'] ?? 'placidus',
    ];
@endphp

<style>
    .report-document { width: min(100%, 210mm); margin: -2rem auto; background: #fff; color: #202020; font-family: Aptos, 'Segoe UI', sans-serif; }
    .report-page { width: 100%; min-height: 297mm; padding: 20mm 18mm; border-bottom: 1px solid #eee; }
    .report-cover { min-height: 297mm; display: flex; flex-direction: column; justify-content: space-between; text-align: center; background: #fffdf9; }
    .report-brand { letter-spacing: .18em; font: 700 .75rem Aptos, 'Segoe UI', sans-serif; color: #6d5a48; }
    .report-cover h1 { margin: 5rem 0 1rem; font: 400 3.2rem/1.1 Aptos, 'Segoe UI', sans-serif; letter-spacing: 0; color: #1d1d1d; }
    .report-page.report-cover h2 { display: block; width: auto; min-width: 0; max-width: none; margin: 0; padding: 0; background: transparent; box-shadow: none; color: #695c52; font: 400 1.35rem/1.5 Aptos, 'Segoe UI', sans-serif; text-align: center; }
    .report-cover > div:first-child { display: flex; flex: 1; flex-direction: column; }
    .report-cover-kicker { margin-top: 1.5rem; }
    .report-cover-name { margin-top: auto; padding-top: 1rem; font-size: .92rem; line-height: 1.2; }
    .report-cover-visuals { display: block; width: 100%; margin: .75rem auto .5rem; background: #fffdf9; }
    .report-cover-visuals > div { display: block; width: 100%; padding: 0; border: 0; }
    .report-cover-wheel-cell, .report-cover-matrix-cell { width: 100%; }
    .report-cover-matrix-cell { padding: .4rem 0 0 !important; }
    .report-cover-wheel { width: min(100%, 405px); margin: .35rem auto .5rem; padding: .5rem; background: #fffdf9; border: 1px solid #d8cabc; border-radius: 0; }
    .report-cover-wheel-container { width: 100%; aspect-ratio: 1; }
    .report-cover-wheel-image { display: block; width: 100%; height: auto; }
    .report-cover-wheel-actions { display: flex; justify-content: center; margin-top: .75rem; }
    .report-cover .aspect-section--cover { max-width: 135mm; margin: 0 auto; padding: .35rem; background: #fffdf9; text-align: left; }
    .report-cover .aspect-section--cover h2 { margin: 0 0 .35rem; font-size: .9rem; text-align: left; }
    .report-cover .aspect-section--cover .aspect-matrix-layout { display: grid; grid-template-columns: minmax(0, 2.2fr) minmax(0, 1fr); gap: .5rem; background: #fffdf9; }
    .report-cover .aspect-section--cover .aspect-matrix-scroll { overflow: visible; }
    .report-cover .aspect-section--cover .aspect-matrix-table { width: 100%; min-width: 0; margin: 0; border: 0; border-radius: 0; background: #fffdf9; font-size: .62rem; }
    .report-cover .aspect-section--cover .aspect-matrix-table th,
    .report-cover .aspect-section--cover .aspect-matrix-table td { width: 3.4mm; min-width: 0; height: 3.4mm; padding: 0; border: .25pt solid #c9c7c1; border-radius: 0; background: #fffdf9; text-align: center; }
    .report-cover .aspect-section--cover .aspect-matrix-table td.aspect-empty { border: 0; }
    .report-cover .aspect-section--cover .aspect-matrix-table td.aspect-diagonal { background: #eef1f2; }
    .report-cover .aspect-section--cover .aspect-glyph { font-family: DejaVu Sans, sans-serif; font-size: 6.5pt; }
    .report-cover .aspect-section--cover .aspect-position { grid-template-columns: minmax(0, 1fr) auto; gap: .1rem; padding: .1rem 0; font-size: .55rem; line-height: 1.05; }
    .report-cover .aspect-section--cover .aspect-position dt { white-space: normal; overflow-wrap: break-word; }
    .report-cover .aspect-section--cover .aspect-position dd { white-space: nowrap; }
    .report-cover .aspect-section--cover .aspect-position dd { font-size: .52rem; }
    .report-cover .aspect-section--cover .aspect-position-glyph { width: 1rem; }
    .report-cover .aspect-section--cover .aspect-glyph--conjunction { color: #c21870; }
    .report-cover .aspect-section--cover .aspect-glyph--opposition,
    .report-cover .aspect-section--cover .aspect-glyph--square { color: #c23b36; }
    .report-cover .aspect-section--cover .aspect-glyph--trine { color: #236bb2; }
    .report-cover .aspect-section--cover .aspect-glyph--sextile { color: #16834b; }
    .report-cover .aspect-section--cover .aspect-positions { display: block; margin: 0; background: #fffdf9; }
    .report-wheel-refresh { border: 1px solid #b58b67; border-radius: 5px; padding: .55rem .9rem; background: #fffaf5; color: #674b39; cursor: pointer; font: 600 .8rem Aptos, 'Segoe UI', sans-serif; }
    .report-cover-meta { color: #6f665f; font: .78rem/1.35 Aptos, 'Segoe UI', sans-serif; }
    .report-kicker { display: inline-block; padding: .55rem 1.1rem; border: 1px solid #cbbba9; color: #695545; font: 700 .72rem Aptos, 'Segoe UI', sans-serif; letter-spacing: .12em; text-transform: uppercase; }
    .report-page h2 { display: table; width: fit-content; min-width: 52%; max-width: 100%; margin: 0 0 2rem; padding: .7rem 1.4rem; background: #f8dfcc; box-shadow: 4px 4px 0 rgba(122, 86, 61, .12); color: #202020; font: 700 1.2rem/1.3 Aptos, 'Segoe UI', sans-serif; text-align: center; }
    .report-page h3 { margin: 2.5rem 0 1rem; font: 700 1.2rem/1.3 Aptos, 'Segoe UI', sans-serif; }
    .report-block-body h4 { font-weight: 700; }
    .report-page p { max-width: 72ch; margin: 0 auto 1.25rem; text-indent: 1.5em; font-size: 1rem; line-height: 1.75; }
    .report-page p, .report-page li { orphans: 3; widows: 3; }
    .report-page h1, .report-page h2, .report-page h3, .report-page h4, .report-block-title, .report-arrow-title { page-break-after: avoid; }
    .report-tail-group { page-break-inside: avoid; }
    .report-page ul, .report-page ol { max-width: 68ch; margin: 1rem auto 1.5rem; padding-left: 1.5rem; line-height: 1.7; }
    .report-page ul { list-style: disc; }
    .report-block-body > ul { page-break-before: avoid; }
    .report-index-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    .report-index-item { display: block; padding: 1.1rem; border-bottom: 1px solid #ded7cf; }
    .report-index-item-pdf { position: relative; padding-right: 3rem; }
    .report-index-item a { color: inherit; text-decoration: none; }
    .report-index-item a:hover strong, .report-index-item a:focus-visible strong { text-decoration: underline; }
    .report-index-item strong { display: block; font-size: 1.05rem; }
    .report-index-item span { color: #766b64; font: .88rem Aptos, 'Segoe UI', sans-serif; }
    .report-index-page { display: none; color: #fff; font: 700 .9rem Aptos, 'Segoe UI', sans-serif; text-align: right; }
    .report-index-item .report-index-page { color: #fff; }
    .report-gray { background: transparent; padding: 0; margin: 1.5rem 0; }
    .report-gray p { max-width: min(601px, calc(100% - 2rem)); font-family: Aptos, 'Segoe UI', sans-serif; font-size: .95rem; }
    .report-arrow-title { display: inline-block; padding: .65rem 0; background: transparent; font: 700 1rem Aptos, 'Segoe UI', sans-serif; }
    .report-door-intro-title { margin-bottom: 2rem; }
    .report-door { page-break-before: always; }
    .report-door-heading { border-bottom: 3px solid #e7cbb5; padding-bottom: 1rem; margin-bottom: 2rem; }
    .report-door-heading h1 { margin-bottom: .5rem; }
    .report-door-subtitle { color: #775c4d; font-style: italic; font-size: 1.15rem; }
    .report-block { margin: 2.5rem 0; }
    .report-block-title { display: inline-block; min-width: 52%; padding: .7rem 1.4rem; background: #f8dfcc; box-shadow: 4px 4px 0 rgba(122, 86, 61, .12); font: 700 1.1rem Aptos, 'Segoe UI', sans-serif; }
    .report-block-body { padding-top: 1.3rem; }
    .report-table { width: 100%; border-collapse: collapse; margin: 1.5rem 0 2rem; font: .9rem Aptos, 'Segoe UI', sans-serif; }
    .report-table th { background: #f0e9df; text-align: left; }
    .report-table th, .report-table td { padding: .8rem; border: 1px solid #d7d1ca; vertical-align: top; }
    .report-table thead { display: table-header-group; }
    .report-table tr, .report-page li, .report-index-item, .report-note { page-break-inside: avoid; }
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
    .report-brand, .report-kicker { font-size: 8.5pt; }
    .report-cover-visuals { width: 100%; margin: 1mm auto; }
    .report-cover-wheel-cell, .report-cover-matrix-cell { width: 100%; }
    .report-cover-matrix-cell { padding: 3mm 0 0 !important; }
    .report-cover-wheel { display: block; width: 90mm; max-width: 100%; margin: 0 auto 1mm; padding: 0; border: 0; background: transparent; }
    .report-cover-wheel-container { width: 90mm; height: 90mm; background: transparent; }
    .report-cover-wheel-image { display: block; width: 90mm; height: 90mm; max-width: none; background: transparent; }
    .report-cover-wheel-actions { display: none; }
    .report-cover-kicker { margin-top: 1mm; }
    .report-cover h1 { margin: 1mm 0; font-size: 25pt; }
    .report-page.report-cover h2 { font-size: 13pt; line-height: 1.25; }
    .report-cover .aspect-section--cover { width: 128mm; max-width: 100%; margin: 0 auto; padding: 0; background: #fffdf9; }
    .report-cover .aspect-section--cover h2 { margin: 0 0 .5mm; font-size: 8pt; }
    .report-cover .aspect-section--cover .aspect-matrix-layout { display: table; width: 100%; table-layout: fixed; background: #fffdf9; }
    .report-cover .aspect-section--cover .aspect-matrix-scroll,
    .report-cover .aspect-section--cover aside { display: table-cell; vertical-align: top; }
    .report-cover .aspect-section--cover .aspect-matrix-scroll { width: 50%; overflow: visible; }
    .report-cover .aspect-section--cover aside { width: 50%; padding-left: 1mm; }
    .report-cover .aspect-section--cover aside h3 { margin: 0 0 1mm; font-size: 8pt; }
    .report-cover .aspect-section--cover .aspect-position { display: table; width: 100%; table-layout: fixed; gap: 0; padding: .1mm 0; font-size: 6.8pt; line-height: 1.1; }
    .report-cover .aspect-section--cover .aspect-position dt,
    .report-cover .aspect-section--cover .aspect-position dd { display: table-cell; vertical-align: middle; }
    .report-cover .aspect-section--cover .aspect-position dt { width: 50%; }
    .report-cover .aspect-section--cover .aspect-position-glyph { display: inline-block; width: 3mm; font-size: 6.8pt; }
    .report-cover .aspect-section--cover .aspect-position dd { width: 50%; margin: 0; font-size: 6.8pt; text-align: right; }
    .report-cover .aspect-section--cover .aspect-matrix-table { font-size: 6.5pt; }
    .report-cover .aspect-section--cover .aspect-matrix-table th,
    .report-cover .aspect-section--cover .aspect-matrix-table td { width: 2.7mm; height: 2.7mm; font-size: 6.5pt; }
    .report-cover .aspect-section--cover .aspect-matrix-table th,
    .report-cover .aspect-section--cover .aspect-matrix-table td.aspect-diagonal,
    .report-cover .aspect-section--cover .aspect-position-glyph { font-family: DejaVu Sans, sans-serif; }
    .report-pdf-only { display: block; }
    .report-index-item .report-index-page { position: absolute; top: 1.1rem; right: 1rem; display: block; width: 1.5rem; color: #fff; }
    .report-pdf-indicator { display: block; margin: 1.2rem 0 .65rem; color: #795c48; font: 700 10pt/1.3 Aptos, 'Segoe UI', sans-serif; letter-spacing: .08em; }
    .report-pdf-indicator::before { content: '> '; color: #b58b67; }
    .report-arrow-title { display: inline-block; margin: 1.2rem 0 .65rem; color: #795c48; font: 700 10pt/1.3 Aptos, 'Segoe UI', sans-serif; letter-spacing: .08em; }
    .report-door-intro-title { margin-bottom: 2rem; }
    .report-arrow-title::before { content: '> '; color: #b58b67; }

    @media screen and (max-width: 700px) {
        .report-index-grid { grid-template-columns: 1fr; }
        .report-page { padding-left: 10mm; padding-right: 10mm; }
    }
    /* Dompdf merges html styles into @page: never reset the root margin. */
    body { margin: 0; padding: 0; background: #fff; }
    .page-shell, .card { max-width: none; margin: 0; padding: 0; border: 0; border-radius: 0; background: #fff; box-shadow: none; }
    .report-document { width: 174mm; margin: 0 auto; padding: 0; font-size: 10.5pt; line-height: 1.48; }
    .report-page { width: auto; min-height: 0; height: auto; margin: 0; padding: 0 10mm; border: 0; page-break-after: always; box-sizing: border-box; }
    .report-page.report-cover { padding: 4mm 10mm; }
    .report-page > h1, .report-page > .report-arrow-title, .report-door > .report-door-heading { padding-top: 0; }
    /* dompdf no soporta flexbox/grid: se reemplazan por posicionamiento de bloque compatible con A4 */
    /* Dompdf reserves the 28mm vertical padding in addition to this content height. */
    .report-cover { display: block; position: relative; min-height: 0; height: 250mm; padding: 4mm 10mm; box-sizing: border-box; page-break-after: always; background: #fffdf9; }
    .report-cover > div:first-child { display: block; }
    .report-worksheet { page-break-inside: avoid; }
    .report-expansion-closing > h2 { margin-top: 2rem; }
    .report-expansion-closing > p { page-break-inside: avoid; }
    .report-expansion-closing > ol > li { page-break-inside: avoid; }
    .report-cover-kicker { margin-top: .5rem; }
    .report-cover h1 { margin: 2mm 0; font-size: 28pt; }
    .report-cover-name { position: static; margin: 5mm 0 0; padding: 0; font-size: 9.5pt; line-height: 1.2; }
    .report-cover-meta { position: absolute; left: 10mm; right: 10mm; bottom: 3mm; margin-top: 0; font-size: 8.5pt; line-height: 1.3; }
    .report-index-grid { display: block; }
    .report-index-item { display: inline-block; width: 47%; margin: 0 1.5% 1rem; vertical-align: top; }
    .report-index-item a { color: inherit; text-decoration: none; }
    .report-door { page-break-before: auto; }
    .report-chapter { page-break-before: auto; }
    .report-last-page { page-break-after: auto; }
    .report-door-heading { page-break-inside: avoid; page-break-after: avoid; }
    .report-page h2 { font-size: 14pt; background: #f8dfcc; } .report-page h3 { font-size: 12pt; }
    .report-page p { max-width: 72ch; font-size: 10.5pt; line-height: 1.48; }
    .report-door-subtitle { font-size: 12pt; }
    .report-gray p { font-size: 10.5pt; line-height: 1.48; }
    .report-block { margin: 18pt 0; page-break-inside: auto; }
    .report-block-title + .report-block-body, .report-block-body > h3 { page-break-before: avoid; }
    .report-block-title { padding: 6pt 10pt; font-size: 12pt; background: #f8dfcc; box-shadow: 3pt 3pt 0 rgba(122, 86, 61, .12); }
    .report-table { font-size: 10pt; }
    .report-table th, .report-table td { padding: 5pt; }
    .report-note { display: block; font-size: 10.5pt; line-height: 1.48; }
    .report-document, .report-document * { background-color: #fff; background-image: none; box-shadow: none; text-shadow: none; }
    .report-document .report-cover,
    .report-document .report-cover .report-cover-visuals,
    .report-document .report-cover .report-cover-wheel,
    .report-document .report-cover .report-cover-wheel-container,
    .report-document .report-cover .report-cover-wheel-image,
    .report-document .report-cover .aspect-section--cover,
    .report-document .report-cover .aspect-section--cover .aspect-matrix-layout,
    .report-document .report-cover .aspect-section--cover .aspect-matrix-table,
    .report-document .report-cover .aspect-section--cover .aspect-matrix-table th,
    .report-document .report-cover .aspect-section--cover .aspect-matrix-table td,
    .report-document .report-cover .aspect-section--cover .aspect-positions { background: #fffdf9 !important; }
    .report-document .report-cover * { background-color: #fffdf9 !important; }
    .report-document .report-cover .aspect-section--cover .aspect-matrix-table td.aspect-diagonal { background-color: #eef1f2 !important; }
    .report-document .report-table th, .report-document .report-note { background: #fff; box-shadow: none; }
    @endif
    @media print {
        /* Original heading appearance from 7ec25a2, before responsive and ink-saving overrides. */
        .report-page .report-block-title, .report-page .report-section-title {
            background: #f8dfcc;
            box-shadow: 4px 4px 0 rgba(122, 86, 61, .12);
        }
    }
    .report-reading .report-block { margin: 10pt 0 12pt; }
    .report-reading .report-block-body { padding-top: 0; }
    .report-reading p { margin-top: 0; margin-bottom: 6pt; }
    .report-reading ul, .report-reading ol { margin-top: 4pt; margin-bottom: 8pt; line-height: 1.48; }
    .report-reading h2 { margin-top: 8pt; margin-bottom: 6pt; }
    .report-reading h3 { margin-top: 9pt; margin-bottom: 4pt; }
    .report-reading .report-block-body > h3 { display: table; padding: 6pt 10pt; background: #f8dfcc; box-shadow: 3pt 3pt 0 rgba(122, 86, 61, .12); }
    .report-reading h4 { margin-top: 7pt; margin-bottom: 4pt; }
    .report-reading .report-door-heading { margin-bottom: 12pt; padding-bottom: 8pt; }
    .report-reading .report-gray { margin: 8pt 0; }
    .report-reading .report-index-item { padding-top: 5pt; padding-bottom: 5pt; margin-bottom: 5pt; }
    @if ($pdf)
    .report-page.report-reading { padding-left: 0; padding-right: 0; }
    .report-page.report-door, .report-page.report-chapter { padding-top: 18mm; }
    .report-reading .report-block { margin: 6pt 0 8pt; }
    .report-reading p { margin-bottom: 4pt; line-height: 1.35; }
    .report-reading ul, .report-reading ol { margin-top: 3pt; margin-bottom: 5pt; line-height: 1.35; }
    .report-reading h2 { margin-top: 5pt; margin-bottom: 4pt; padding: 5pt 10pt; }
    .report-reading h3 { margin-top: 6pt; margin-bottom: 3pt; }
    .report-reading h4 { margin-top: 5pt; margin-bottom: 3pt; }
    @endif
</style>

<div class="report-document">
    <section class="report-page report-cover">
        <div>
            <div class="report-brand">ASTROLOGÍA SARANA VEDA</div>
            <div class="report-cover-kicker"><span class="report-kicker">Mapa interior</span></div>
            <h1>Carta natal de<br>{{ $report['name'] }}</h1>
            <h2>Informe I<br>Sol · Luna · Ascendente · Descendente<br>Opuestos complementarios</h2>
            <div class="report-cover-visuals">
                <div class="report-cover-wheel-cell">
                    <div class="report-cover-wheel" data-wheel-panel>
                        <div class="report-cover-wheel-container nocturna-wheel-container" data-background-color="#fffdf9" @if (!$pdf && (empty($wheelImage) || ! str_starts_with($wheelImage, 'data:image/png;base64,'))) data-natal-wheel data-chart="{{ json_encode($coverWheelData + ['backgroundColor' => '#fffdf9'], JSON_HEX_APOS | JSON_HEX_QUOT) }}" @endif>
                            @if (!$pdf && ! empty($wheelImage) && str_starts_with($wheelImage, 'data:image/png;base64,'))
                                <img class="report-cover-wheel-image" src="{{ $wheelImage }}" alt="Rueda astrológica">
                            @endif
                            @if ($pdf && ! empty($wheelImage))
                                <img class="report-cover-wheel-image" src="{{ $wheelImage }}" alt="Rueda astrológica">
                            @endif
                        </div>
                        @if ($pdf || empty($wheelImage) || ! str_starts_with($wheelImage, 'data:image/png;base64,'))
                            <div class="report-cover-wheel-actions"><button type="button" class="report-wheel-refresh" data-refresh-natal-wheel>Actualizar rueda astrológica</button></div>
                        @endif
                    </div>
                </div>
                <div class="report-cover-matrix-cell">
                    @include('components.aspect-matrix', $coverAspectMatrix + ['variant' => 'cover', 'showPositions' => true, 'pdf' => $pdf])
                </div>
            </div>
            <div class="report-cover-name">Identidad y voluntad<br>Necesidades emocionales<br>Ritmo propio y vínculos</div>
        </div>
        <div class="report-cover-meta">{{ $report['technical']['birth_date'] }} · {{ $report['technical']['birth_time'] }} · {{ $report['technical']['place'] }}<br>Zodiaco {{ $report['technical']['zodiac'] }} · Casas {{ $report['technical']['houses'] }}</div>
    </section>
    <section class="report-page report-reading"><h1>Índice</h1>
        <div class="report-index-grid">@foreach ($report['index'] as $item)<div class="report-index-item {{ $pdf ? 'report-index-item-pdf' : '' }}"><a href="#report-section-{{ $item['key'] }}"><strong>{{ $item['title'] }}</strong></a>@if ($pdf)<span class="report-index-page" data-index-page-label="{{ $item['key'] }}">&#160;</span>@endif</div>@endforeach</div>
        @unless ($report['complete'])<div class="report-note">Este Informe I todavía no está generado con el Manual Maestro Universal. Regenera el informe completo de cinco bloques.</div>@endunless
    </section>
    @foreach ($report['doors'] as $door)
        @continue(!($report['sections'][$door['key']]['enabled'] ?? false))
        <section class="report-page report-door report-reading" id="report-section-{{ $door['key'] }}" data-door-key="{{ $door['key'] }}" data-index-key="{{ $door['key'] }}">
            <div class="report-door-heading"><h1>{{ $door['title'] }}</h1><div class="report-door-subtitle">{{ $door['subtitle'] }}</div></div>
            @foreach ($door['blocks'] as $block => $paragraphs)
                <div class="report-block report-block-body" data-section-id="{{ $door['key'] }}.{{ $block }}">
                    @foreach ($paragraphs as $html)
                        {!! $html !!}
                    @endforeach
                </div>
            @endforeach
        </section>
    @endforeach
    <section class="report-page report-chapter report-last-page report-reading" id="report-section-opposites" data-index-key="opposites">
        <div class="report-door-heading"><h1>{{ $report['sections']['opposites']['title'] }}</h1></div>
        @foreach ($report['opposites'] as $block => $paragraphs)
            <div class="report-block report-block-body" data-section-id="opposites.{{ $block }}">@foreach ($paragraphs as $html){!! $html !!}@endforeach</div>
        @endforeach
    </section>
</div>
