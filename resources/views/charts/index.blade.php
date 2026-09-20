@extends('layouts.app')

@section('title', 'Cartas guardadas')

@section('content')
<style>
    .chart-search-form { display: flex; align-items: center; gap: .65rem; flex-wrap: wrap; margin: 1rem 0; }
    .chart-search-form input { flex: 1 1 250px; width: auto; min-width: 250px; margin: 0; }
    .chart-search-form button { flex: 0 0 auto; height: 2.35rem; margin: 0; padding: .55rem .9rem; }
    .chart-search-form a { flex: 0 0 auto; }
    .chart-status { display: flex; flex-wrap: wrap; gap: .35rem; }
    .chart-status-badge { display: inline-flex; align-items: center; min-height: 1.75rem; padding: .3rem .55rem; border: 1px solid #b58b67; border-radius: 999px; background: #fffaf5; color: #674b39; font-size: .78rem; font-weight: 500; white-space: nowrap; }
    .chart-status-badge.report { border-color: #5f8066; background: #f1f7f1; color: #416247; }
</style>

    <h1>Cartas guardadas</h1>

    @if (session('success'))
        <p style="color: green; font-weight: 600;">{{ session('success') }}</p>
    @endif

    <form class="chart-search-form" method="GET" action="{{ route('charts.index') }}">
        <input
            type="text"
            name="search"
            value="{{ old('search', $search ?? '') }}"
            placeholder="Buscar por nombre o alias"
        >
        <button type="submit">Buscar</button>
        @if ($search)
            <a href="{{ route('charts.index') }}">Limpiar</a>
        @endif
    </form>

    <table style="border-collapse: collapse; width: 100%;">
        <tr>
            <th style="vertical-align: middle;">#</th>
            <th style="vertical-align: middle;">Persona</th>
            <th style="vertical-align: middle;">Estado</th>
            <th style="vertical-align: middle;">Fecha de cálculo</th>
            <th style="text-align: center; vertical-align: middle;">Acciones</th>
        </tr>
        @forelse ($charts as $chart)
            <tr>
                <td style="vertical-align: middle;">{{ $chart->id }}</td>
                <td style="vertical-align: middle;">
                    <a href="{{ route('charts.show', $chart) }}">
                        @if ($chart->person->full_name)
                            {{ $chart->person->full_name }}@if ($chart->person->alias) ({{ $chart->person->alias }})@endif
                        @else
                            {{ $chart->person->alias }}
                        @endif
                    </a>
                </td>
                <td style="vertical-align: middle;">
                    <div class="chart-status">
                        @if ($chart->status === 'calculated')
                            <span class="chart-status-badge">Cálculos astrológicos</span>
                        @elseif ($chart->status === 'pending')
                            <span class="chart-status-badge">Pendiente</span>
                        @elseif ($chart->status === 'failed')
                            <span class="chart-status-badge">Fallido</span>
                        @else
                            <span class="chart-status-badge">{{ ucfirst($chart->status) }}</span>
                        @endif
                        @if ($chart->interpretations->contains('phase', 'fase-1'))
                            <span class="chart-status-badge report">Informe generado</span>
                        @endif
                    </div>
                </td>
                <td style="vertical-align: middle;">{{ $chart->created_at->format('d/m/Y H:i') }}</td>
                <td style="text-align: center; vertical-align: middle;">
                    <div style="display: flex; justify-content: center; align-items: center; gap: .45rem;">
                        @if ($chart->interpretations->contains('phase', 'fase-1'))
                            <a class="file-download-action" href="{{ route('charts.report.download', $chart) }}" title="Descarga Informe Carta Natal Fase 1" aria-label="Descarga Informe Carta Natal Fase 1">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path d="M6 2.75h8l4 4V21.25H6z" />
                                    <path d="M14 2.75v4h4M12 10v6m0 0-2.5-2.5M12 16l2.5-2.5" />
                                </svg>
                            </a>
                        @endif
                        <a class="file-download-action" href="{{ route('charts.registration.edit', $chart) }}" title="Editar datos de registro" aria-label="Editar datos de registro"><span aria-hidden="true">✎</span></a>
                        <a class="file-download-action" href="{{ route('charts.report.history', $chart) }}" title="Histórico de generación de PDF" aria-label="Histórico de generación de PDF"><span aria-hidden="true">↧</span></a>
                        <a class="file-download-action" href="{{ route('charts.person.history', $chart) }}" title="Histórico de cambios de la persona" aria-label="Histórico de cambios de la persona"><span aria-hidden="true">◷</span></a>
                        <form method="POST" action="{{ route('charts.destroy', $chart) }}" data-confirm-message="¿Seguro que quieres eliminar esta carta natal?" style="margin: 0; display: flex; justify-content: center; align-items: center;">
                            @csrf
                            @method('DELETE')
                            <button class="delete-action" type="submit" title="Eliminar carta natal" aria-label="Eliminar carta natal">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path d="M4 7h16M9 7V4.5h6V7M7 7l.75 13h8.5L17 7M10 10.5v6M14 10.5v6" />
                                </svg>
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
        @empty
            <tr><td colspan="5">Todavía no hay cartas calculadas.</td></tr>
        @endforelse
    </table>

    {{ $charts->links() }}
@endsection

@section('floating_actions')
    <nav class="app-action-bar" aria-label="Acciones de cartas">
        <a class="app-action app-action-primary" href="{{ route('charts.create') }}">Nueva carta natal</a>
    </nav>
@endsection
