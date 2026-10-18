@extends('layouts.app')
@section('title', 'Prompts IA - ' . ($generation->door ?: 'integración'))
@section('content')
<h1>Prompts enviados a OpenAI</h1>
<p>{{ $chart->person->full_name ?: $chart->person->alias }} · {{ ucfirst($generation->door ?? 'integración') }} · {{ $generation->ai_model }} · {{ $generation->created_at->format('d/m/Y H:i:s') }}</p>
<p class="note">Estos son los textos exactos enviados en esta generación. El único prompt maestro se configura en el sistema. El <a href="{{ route('charts.report.edit', $chart) }}">editor del informe</a> modifica el contenido de esta carta, no las instrucciones de futuras generaciones.</p>
<h2>System prompt</h2>
<pre style="white-space: pre-wrap; overflow-wrap: anywhere; max-height: 38rem; overflow: auto; padding: 1rem; background: #f7f2ec; border: 1px solid #ded7cf;">{{ $generation->system_prompt }}</pre>
<h2>User prompt</h2>
<pre style="white-space: pre-wrap; overflow-wrap: anywhere; max-height: 38rem; overflow: auto; padding: 1rem; background: #f7f2ec; border: 1px solid #ded7cf;">{{ $generation->user_prompt }}</pre>
@endsection
@section('floating_actions')<nav class="app-action-bar"><a class="app-action" href="{{ route('charts.report.history', $chart) }}">Volver al histórico</a><a class="app-action app-action-primary" href="{{ route('charts.report.edit', $chart) }}">Editar informe</a></nav>@endsection