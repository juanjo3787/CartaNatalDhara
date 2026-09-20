@extends('layouts.app')
@section('title', 'Histórico de PDF')
@section('content')
<h1>Histórico de generación de PDF</h1>
<p>{{ $chart->person->full_name ?: $chart->person->alias }}</p>
<table><tr><th>Fecha</th><th>Informe</th><th>Tamaño</th><th>Checksum</th><th>Acción</th></tr>
@forelse ($generations as $generation)<tr><td>{{ $generation->created_at->format('d/m/Y H:i:s') }}</td><td>{{ $generation->filename }}</td><td>{{ number_format($generation->size_bytes / 1024, 1) }} KB</td><td>{{ substr($generation->checksum, 0, 12) }}...</td><td><a href="{{ route('charts.report.download', $chart) }}">Descargar actual</a></td></tr>@empty<tr><td colspan="5">No hay generaciones registradas.</td></tr>@endforelse</table>
@endsection
@section('floating_actions')<nav class="app-action-bar"><a class="app-action" href="{{ route('charts.index') }}">Ver cartas</a><a class="app-action app-action-primary" href="{{ route('charts.show', $chart) }}">Volver a la carta</a></nav>@endsection
