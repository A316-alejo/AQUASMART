@extends('layouts.app')
@section('title', 'Estadísticas — AquaSmart')

@php
    $int = fn ($value) => number_format((int) $value, 0, ',', '.');
    $dec = fn ($value, $decimals = 2) => $value === null ? '—' : number_format((float) $value, $decimals, ',', '.');
    $chartId = 0;
@endphp

@section('content')
    <div class="page-head">
        <div>
            <h1>Estadísticas</h1>
            <p>Análisis histórico de la producción, calidad del agua y alertas de lo que puedes consultar.</p>
        </div>
        <div class="actions no-print">
            <button type="button" class="btn" onclick="window.print()">Imprimir resumen</button>
        </div>
    </div>

    <div class="card no-print">
        <form class="filters" method="GET" action="{{ route('stats') }}">
            <x-form.select name="farm" label="Finca" placeholder="Todas" :options="$farms" :value="$filters['farm'] ?? null" />
            <x-form.select name="pond" label="Estanque" placeholder="Todos" :options="$ponds" :value="$filters['pond'] ?? null" />
            <x-form.select name="batch" label="Lote" placeholder="Todos" :options="$batchOptions" :value="$filters['batch'] ?? null" />
            <x-form.select name="metric" label="Parámetro" placeholder="Todos" :options="$metrics" :value="$filters['metric'] ?? null" />
            <x-form.input name="from" label="Desde" type="date" :value="$from->toDateString()" />
            <x-form.input name="to" label="Hasta" type="date" :value="$to->toDateString()" />
            <div class="btns">
                <button type="submit" class="btn">Filtrar</button>
                @if (array_filter($filters))
                    <a class="btn ghost" href="{{ route('stats') }}">Limpiar</a>
                @endif
            </div>
        </form>
    </div>

    @if ($rangeClamped)
        <div class="notice info" role="status">El rango de fechas se ajustó a un máximo de 366 días.</div>
    @endif

    <p class="muted-line" style="margin-bottom:14px">
        Periodo analizado: {{ $from->format('d/m/Y') }} – {{ $to->format('d/m/Y') }}
        · {{ $int($production['batches']) }} lote(s) considerado(s)
    </p>

    @unless ($hasData)
        <div class="card">
            <div class="empty">
                <div class="glyph"><x-icon name="stats" /></div>
                <h3>No hay datos para estos filtros</h3>
                <p>No tienes fincas, estanques o lotes disponibles con esta combinación de filtros. Prueba quitando alguno.</p>
            </div>
        </div>
    @else
        {{-- Producción --}}
        <div class="card" style="margin-bottom:14px">
            <header><h3>Producción</h3></header>
            <div class="grid g3" style="margin-bottom:10px">
                <div class="metric">
                    <div class="m-lab">Peces sembrados</div>
                    <div class="m-val">{{ $int($production['stocked']) }}</div>
                </div>
                <div class="metric">
                    <div class="m-lab">Mortalidad acumulada</div>
                    <div class="m-val">{{ $int($production['cumulativeMortality']) }}</div>
                </div>
                <div class="metric">
                    <div class="m-lab">Peces vivos estimados</div>
                    <div class="m-val">{{ $int($production['liveFish']) }}</div>
                </div>
            </div>
            <div class="grid g3">
                <div class="metric">
                    <div class="m-lab">Biomasa estimada</div>
                    <div class="m-val">{{ $dec($production['biomassKg']) }} <small>kg</small></div>
                </div>
                <div class="metric">
                    <div class="m-lab">Supervivencia</div>
                    <div class="m-val">{{ $production['survivalPct'] !== null ? $dec($production['survivalPct'], 1).'%' : '—' }}</div>
                </div>
                <div class="metric">
                    <div class="m-lab">Peso promedio actual</div>
                    <div class="m-val">{{ $production['averageWeightG'] !== null ? $dec($production['averageWeightG']).' g' : '—' }}</div>
                </div>
            </div>
        </div>

        {{-- Alimentación --}}
        <div class="card" style="margin-bottom:14px">
            <header>
                <h3>Alimentación</h3>
                <span class="tag info plain">{{ $dec($feeding['totalKg']) }} kg</span>
            </header>
            @if ($feeding['totalKg'] <= 0)
                <p class="muted-line">No hay registros de alimentación en el período seleccionado.</p>
            @else
                @php $chartId++; @endphp
                <div data-chart="chart-{{ $chartId }}" style="margin-bottom:16px"></div>
                <script type="application/json" id="chart-{{ $chartId }}">@json($feeding['chart'])</script>

                <h4 style="margin:0 0 6px;font-size:13.5px;color:var(--ink-2)">Alimentación por lote</h4>
                @forelse ($feeding['byBatch'] as $row)
                    <div class="list-row">
                        <span class="body">
                            <b>{{ $row['batch']->code }} · {{ $row['batch']->name }}</b>
                        </span>
                        <span>{{ $dec($row['totalKg']) }} kg</span>
                    </div>
                @empty
                    <p class="muted-line">Sin datos por lote en este período.</p>
                @endforelse
            @endif
        </div>

        {{-- Mortalidad --}}
        <div class="card" style="margin-bottom:14px">
            <header>
                <h3>Mortalidad</h3>
                <span class="tag info plain">{{ $int($mortality['total']) }}</span>
            </header>
            @if ($mortality['total'] <= 0)
                <p class="muted-line">No hay registros de mortalidad en el período seleccionado.</p>
            @else
                @php $chartId++; @endphp
                <div data-chart="chart-{{ $chartId }}" style="margin-bottom:16px"></div>
                <script type="application/json" id="chart-{{ $chartId }}">@json($mortality['chart'])</script>

                <div class="grid g-2-1">
                    <div>
                        <h4 style="margin:0 0 6px;font-size:13.5px;color:var(--ink-2)">Mortalidad por causa</h4>
                        @forelse ($mortality['byCause'] as $row)
                            <div class="list-row">
                                <span class="body"><b>{{ $row['cause'] }}</b></span>
                                <span>{{ $int($row['total']) }}</span>
                            </div>
                        @empty
                            <p class="muted-line">Sin datos por causa.</p>
                        @endforelse
                    </div>
                    <div>
                        <h4 style="margin:0 0 6px;font-size:13.5px;color:var(--ink-2)">Mortalidad por lote</h4>
                        @forelse ($mortality['byBatch'] as $row)
                            <div class="list-row">
                                <span class="body"><b>{{ $row['batch']->code }}</b></span>
                                <span>{{ $int($row['total']) }}</span>
                            </div>
                        @empty
                            <p class="muted-line">Sin datos por lote.</p>
                        @endforelse
                    </div>
                </div>
            @endif
        </div>

        {{-- Calidad del agua --}}
        <div class="card" style="margin-bottom:14px">
            <header>
                <h3>Calidad del agua</h3>
                <span class="tag info plain">{{ $int($waterQuality['total']) }} mediciones</span>
            </header>
            @if ($waterQuality['total'] <= 0)
                <p class="muted-line">No hay mediciones de calidad del agua en el período seleccionado.</p>
            @else
                <div class="grid g3" style="margin-bottom:16px">
                    <div class="metric">
                        <div class="m-lab">Normales</div>
                        <div class="m-val">{{ $int($waterQuality['counts']['normal']) }}</div>
                    </div>
                    <div class="metric">
                        <div class="m-lab">Advertencia</div>
                        <div class="m-val is-warn">{{ $int($waterQuality['counts']['advertencia']) }}</div>
                    </div>
                    <div class="metric">
                        <div class="m-lab">Crítico</div>
                        <div class="m-val is-crit">{{ $int($waterQuality['counts']['critico']) }}</div>
                    </div>
                </div>

                @foreach ($waterQuality['charts'] as $wq)
                    @php $chartId++; @endphp
                    <h4 style="margin:0 0 6px;font-size:13.5px;color:var(--ink-2)">{{ $wq['metric']->name }} ({{ $wq['metric']->unit }})</h4>
                    <div data-chart="chart-{{ $chartId }}" style="margin-bottom:16px"></div>
                    <script type="application/json" id="chart-{{ $chartId }}">@json(['labels' => $wq['labels'], 'series' => $wq['series']])</script>
                @endforeach
            @endif
        </div>

        {{-- Alertas --}}
        <div class="card" style="margin-bottom:14px">
            <header>
                <h3>Alertas</h3>
                <span class="tag info plain">{{ $int($alerts['total']) }}</span>
            </header>
            @if ($alerts['total'] <= 0)
                <p class="muted-line">No hay alertas generadas en el período seleccionado.</p>
            @else
                <div class="grid g4" style="margin-bottom:16px">
                    <div class="metric">
                        <div class="m-lab">Críticas</div>
                        <div class="m-val is-crit">{{ $int($alerts['critical']) }}</div>
                    </div>
                    <div class="metric">
                        <div class="m-lab">Advertencias</div>
                        <div class="m-val is-warn">{{ $int($alerts['warning']) }}</div>
                    </div>
                    <div class="metric">
                        <div class="m-lab">Pendientes</div>
                        <div class="m-val">{{ $int($alerts['pending']) }}</div>
                    </div>
                    <div class="metric">
                        <div class="m-lab">Resueltas</div>
                        <div class="m-val">{{ $int($alerts['resolved']) }}</div>
                    </div>
                </div>

                @php $chartId++; @endphp
                <div data-chart="chart-{{ $chartId }}"></div>
                <script type="application/json" id="chart-{{ $chartId }}">@json($alerts['chart'])</script>
            @endif
        </div>
    @endunless
@endsection
