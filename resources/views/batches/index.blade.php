@extends('layouts.app')
@section('title', 'Lotes — AquaSmart')

@section('content')
    <div class="page-head">
        <div>
            <h1>Lotes de producción</h1>
            <p>{{ $totals ? 'Ciclos de producción de todos los estanques.' : 'Lotes de los estanques a los que tienes acceso.' }}</p>
        </div>
        @can('create', App\Models\Batch::class)
            <div class="actions">
                <a class="btn primary" href="{{ route('batches.create') }}">Nuevo lote</a>
            </div>
        @endcan
    </div>

    @if ($totals)
        <div class="grid g4" style="margin-bottom:14px">
            <div class="card kpi">
                <div class="label">Lotes activos</div>
                <div class="value">{{ number_format((int) $totals->active, 0, ',', '.') }}</div>
                <div class="foot">En cultivo</div>
            </div>
            <div class="card kpi">
                <div class="label">Peces sembrados</div>
                <div class="value">{{ number_format((int) $totals->stocked, 0, ',', '.') }}</div>
                <div class="foot">Cantidad inicial de los lotes activos</div>
            </div>
            <div class="card kpi">
                <div class="label">Cerrados</div>
                <div class="value">{{ number_format((int) $totals->closed, 0, ',', '.') }}</div>
                <div class="foot">Ciclos finalizados</div>
            </div>
            <div class="card kpi">
                <div class="label">Cancelados</div>
                <div class="value">{{ number_format((int) $totals->cancelled, 0, ',', '.') }}</div>
                <div class="foot">Ciclos interrumpidos</div>
            </div>
        </div>
    @endif

    <div class="card">
        <form class="filters" method="GET" action="{{ route('batches.index') }}" role="search">
            <x-form.input name="q" label="Buscar" type="search" :value="$filters['q'] ?? null"
                          placeholder="Código o nombre" maxlength="100" />
            <x-form.select name="farm" label="Finca" placeholder="Todas" :options="$farms" :value="$filters['farm'] ?? null" />
            <x-form.select name="species" label="Especie" placeholder="Todas" :options="$species" :value="$filters['species'] ?? null" />
            <x-form.select name="status" label="Estado" placeholder="Todos" :options="App\Enums\BatchStatus::options()"
                           :value="$filters['status'] ?? null" />
            <div class="btns">
                <button type="submit" class="btn">Filtrar</button>
                @if (array_filter($filters))
                    <a class="btn ghost" href="{{ route('batches.index') }}">Limpiar</a>
                @endif
            </div>
        </form>

        @if ($batches->isEmpty())
            <div class="empty">
                <div class="glyph"><x-icon name="fish" /></div>
                @if (array_filter($filters))
                    <h3>No hay lotes que coincidan</h3>
                    <p>Prueba con otro código, finca, especie o estado.</p>
                @elseif ($totals)
                    <h3>Todavía no hay lotes</h3>
                    <p>Registra la primera siembra en un estanque activo.</p>
                @else
                    <h3>No tienes lotes disponibles</h3>
                    <p>Los lotes de los estanques a los que tienes acceso aparecerán aquí.</p>
                @endif
            </div>
        @else
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">Lote</th>
                            <th scope="col">Estanque</th>
                            <th scope="col">Especie</th>
                            <th scope="col">Siembra</th>
                            <th scope="col" class="num">Sembrados</th>
                            <th scope="col">Estado</th>
                            <th scope="col" class="num">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($batches as $batch)
                            <tr>
                                <td>
                                    <div class="who-cell" style="min-width:170px">
                                        <span>
                                            <b><a class="link" style="color:inherit" href="{{ route('batches.show', $batch) }}">{{ $batch->code }}</a></b>
                                            <span class="mail">{{ $batch->name }}</span>
                                        </span>
                                    </div>
                                </td>
                                <td style="min-width:170px">
                                    {{ $batch->pond->code }} · {{ $batch->pond->name }}
                                    <span class="sub-line">{{ $batch->pond->farm->name }}</span>
                                </td>
                                <td>{{ $batch->species->common_name }}</td>
                                <td class="nowrap">
                                    {{ $batch->stocked_at->format('d/m/Y') }}
                                    <span class="sub-line">{{ $batch->daysInCulture() }} días{{ $batch->isActive() ? '' : ' de ciclo' }}</span>
                                </td>
                                <td class="num nowrap">
                                    {{ number_format($batch->initial_quantity, 0, ',', '.') }}
                                    <span class="sub-line">{{ number_format((float) $batch->initial_average_weight_g, 2, ',', '.') }} g prom.</span>
                                </td>
                                <td><x-batch-status :status="$batch->status" /></td>
                                <td>
                                    <div class="row-actions">
                                        @can('update', $batch)
                                            <a class="btn sm" href="{{ route('batches.edit', $batch) }}">Editar</a>
                                        @else
                                            <a class="btn sm" href="{{ route('batches.show', $batch) }}">Ver</a>
                                        @endcan
                                        @can('close', $batch)
                                            <a class="btn sm" href="{{ route('batches.close.confirm', $batch) }}">Cerrar</a>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{ $batches->links() }}
        @endif
    </div>
@endsection
