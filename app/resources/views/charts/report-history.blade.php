@extends('layouts.app')
@section('title', 'Histórico de PDF')
@section('content')
<h1>Histórico de generaciones y costes</h1>
<p>{{ $chart->person->full_name ?: $chart->person->alias }}</p>
<section class="note" aria-label="Totales de uso de IA">
<strong>Total acumulado de IA</strong>
<p>{{ number_format($totals['tokens']) }} tokens · Base: {{ number_format($totals['subtotal'], 8, ',', '.') }} {{ $totals['currency'] }} · IVA: {{ number_format($totals['tax'], 8, ',', '.') }} {{ $totals['currency'] }} · Total: {{ number_format($totals['total'], 8, ',', '.') }} {{ $totals['currency'] }}</p>
</section>
<table><tr><th>Fecha</th><th>Tipo</th><th>Puerta/modelo</th><th>Tokens</th><th>Coste base</th><th>IVA</th><th>Total</th><th>Acción</th></tr>
@forelse ($generations as $generation)
<tr>
	<td>{{ $generation->created_at->format('d/m/Y H:i:s') }}</td>
	<td>{{ $generation->ai_assisted ? 'IA' : 'PDF' }}</td>
	<td>{{ $generation->ai_assisted ? ucfirst($generation->door) . ' / ' . $generation->ai_model : $generation->filename }}</td>
	<td>{{ $generation->ai_assisted ? number_format($generation->total_tokens ?? 0) : '-' }}</td>
	<td>{{ $generation->ai_assisted ? number_format((float) $generation->cost_subtotal, 8, ',', '.') . ' ' . $generation->cost_currency : '-' }}</td>
	<td>{{ $generation->ai_assisted ? number_format((float) $generation->tax_amount, 8, ',', '.') . ' ' . $generation->cost_currency . ' (' . $generation->tax_rate . '%)' : '-' }}</td>
	<td>{{ $generation->ai_assisted ? number_format((float) $generation->cost_total, 8, ',', '.') . ' ' . $generation->cost_currency : '-' }}</td>
	<td>@if (! $generation->ai_assisted)<a href="{{ route('charts.report.download', $chart) }}">Descargar actual</a>@else - @endif</td>
</tr>
@empty
<tr><td colspan="8">No hay generaciones registradas.</td></tr>
@endforelse</table>
@endsection
@section('floating_actions')<nav class="app-action-bar"><a class="app-action" href="{{ route('charts.index') }}">Ver cartas</a><a class="app-action app-action-primary" href="{{ route('charts.show', $chart) }}">Volver a la carta</a></nav>@endsection
