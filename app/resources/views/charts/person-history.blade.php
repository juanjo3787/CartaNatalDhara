@extends('layouts.app')
@section('title', 'Histórico de cambios')
@section('content')
<h1>Histórico de cambios de la persona</h1>
<p>{{ $chart->person->full_name ?: $chart->person->alias }}</p>
<table><tr><th>Fecha</th><th>Campo</th><th>Valor anterior</th><th>Valor nuevo</th></tr>
@forelse ($changes as $change)<tr><td>{{ $change->created_at->format('d/m/Y H:i:s') }}</td><td>{{ $change->field }}</td><td>{{ $change->old_value ?: 'Vacío' }}</td><td>{{ $change->new_value ?: 'Vacío' }}</td></tr>@empty<tr><td colspan="4">No hay cambios registrados.</td></tr>@endforelse</table>
@endsection
@section('floating_actions')<nav class="app-action-bar"><a class="app-action" href="{{ route('charts.index') }}">Ver cartas</a><a class="app-action app-action-primary" href="{{ route('charts.show', $chart) }}">Volver a la carta</a></nav>@endsection
