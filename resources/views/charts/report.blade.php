@extends('layouts.app')

@section('title', 'Informe Fase 1 - ' . $report['name'])

@section('content')
    @if (session('success'))<div class="note">{{ session('success') }}</div>@endif
    @if (session('error'))<div class="note">{{ session('error') }}</div>@endif
    @include('reports.phase-one.template', ['report' => $report, 'chart' => $chart, 'pdf' => $pdf ?? false])
@endsection

@if (empty($pdf))
@section('floating_actions')
    <style>
        .report-action-bar { position: fixed !important; z-index: 9999; left: 0; right: 0; bottom: 0; display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: .65rem; padding: .7rem max(.7rem, calc((100vw - 1100px) / 2)); border-top: 1px solid #d8cabc; background: rgba(255,253,249,.98); box-shadow: 0 -10px 28px rgba(68,48,33,.18); }
        .report-action { display: inline-flex; align-items: center; justify-content: center; height: 2.35rem; margin: 0; border: 1px solid #b58b67; border-radius: 5px; background: #fffaf5; color: #674b39; text-decoration: none; font: 500 .8rem Arial,sans-serif; font-weight: 500 !important; }
        .report-action-primary { background: #795c48; color: #fff; }
        .report-action-validate { background: #5f8066; color: #fff; border-color: #5f8066; }
        @media (max-width:700px) { .report-action-bar { padding: .45rem; } .report-action { font-size: .72rem; } }
    </style>
    <nav class="report-action-bar" aria-label="Acciones del informe">
        <form method="POST" action="{{ route('charts.report.regenerate', $chart) }}" onsubmit="return confirm('¿Regenerar el informe con el contenido editorial actualizado?');">@csrf<button class="report-action report-action-primary" type="submit">Regenerar informe</button></form>
        <form method="POST" action="{{ route('charts.report.validate', $chart) }}">@csrf<button class="report-action report-action-validate" type="submit">Validar y guardar PDF</button></form>
        <a class="report-action" href="{{ route('charts.report.download', $chart) }}">Descargar PDF</a>
        <a class="report-action report-action-primary" href="{{ route('charts.report.edit', $chart) }}">Editar informe</a>
        <a class="report-action" href="{{ route('charts.show', $chart) }}">Volver a la carta</a>
    </nav>
@endsection
@endif
