@props(['points' => [], 'matches' => [], 'variant' => 'page', 'showPositions' => true, 'pdf' => false])

<section class="aspect-section aspect-section--{{ $variant }}" aria-labelledby="aspect-matrix-heading">
    <h2 id="aspect-matrix-heading">Matriz de aspectos</h2>
    @if (count($points) > 1)
        <div class="aspect-matrix-layout">
            <div class="aspect-matrix-scroll" role="region" aria-label="Matriz triangular desplazable" tabindex="0">
                <table
                    class="aspect-matrix-table"
                    data-aspect-matrix
                    data-points="{{ json_encode($points, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG) }}"
                >
                    @if (! $pdf || $variant !== 'cover')
                        <caption class="sr-only">Aspectos entre los puntos calculados de la carta natal</caption>
                    @endif
                    <thead>
                        <tr>
                            <th scope="col">@if (! $pdf || $variant !== 'cover')<span class="sr-only">Punto</span>@endif</th>
                            @foreach ($points as $point)
                                <th scope="col" title="{{ $point['label'] }} · {{ $point['position'] }}">{{ $point['glyph'] }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($points as $rowIndex => $rowPoint)
                            <tr>
                                <th scope="row" title="{{ $rowPoint['label'] }} · {{ $rowPoint['position'] }}">{{ $rowPoint['glyph'] }}</th>
                                @foreach ($points as $columnIndex => $columnPoint)
                                    @if ($columnIndex < $rowIndex)
                                        @php($pairMatches = $matches[$columnPoint['key'].'|'.$rowPoint['key']] ?? [])
                                        <td data-aspect-cell data-first="{{ $columnPoint['key'] }}" data-second="{{ $rowPoint['key'] }}" aria-label="{{ $columnPoint['label'] }} · {{ $rowPoint['label'] }}: {{ count($pairMatches) ? implode(', ', array_column($pairMatches, 'label')) : 'sin aspecto' }}">
                                            @foreach ($pairMatches as $match)
                                                <span class="aspect-glyph aspect-glyph--{{ $match['key'] }}" title="{{ $match['label'] }} (orbe {{ number_format($match['orb'], 1, ',', '') }}°)">{{ $match['glyph'] }}</span>
                                            @endforeach
                                        </td>
                                    @elseif ($columnIndex === $rowIndex)
                                        <td class="aspect-diagonal">{{ $rowPoint['glyph'] }}</td>
                                    @else
                                        <td class="aspect-empty" aria-hidden="true"></td>
                                    @endif
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($showPositions)
                <aside aria-labelledby="aspect-positions-heading">
                    <h3 id="aspect-positions-heading">Posiciones</h3>
                    <dl class="aspect-positions">
                        @foreach ($points as $point)
                            <div class="aspect-position">
                                <dt><span class="aspect-position-glyph" aria-hidden="true">{{ $point['glyph'] }}</span>{{ $point['label'] }}</dt>
                                <dd>{{ $point['position'] }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </aside>
            @endif
        </div>
    @else
        <p>No hay suficientes posiciones calculadas para generar la matriz.</p>
    @endif
</section>