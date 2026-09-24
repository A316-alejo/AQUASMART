@extends('layouts.app')
@section('title', 'Fincas — AquaSmart')

@section('content')
    <div class="page-head">
        <div>
            <h1>Fincas</h1>
            <p>{{ $manages ? 'Fincas piscícolas registradas en AquaSmart y el personal asignado a cada una.' : 'Fincas a las que tienes acceso.' }}</p>
        </div>
        @can('create', App\Models\Farm::class)
            <div class="actions">
                <a class="btn primary" href="{{ route('farms.create') }}">Nueva finca</a>
            </div>
        @endcan
    </div>

    @if ($totals)
        <div class="grid g4" style="margin-bottom:14px">
            <div class="card kpi">
                <div class="label">Fincas registradas</div>
                <div class="value">{{ number_format($totals->total) }}</div>
                <div class="foot">De todos los productores</div>
            </div>
            <div class="card kpi">
                <div class="label">Activas</div>
                <div class="value">{{ number_format($totals->active) }}</div>
                <div class="foot">En operación</div>
            </div>
            <div class="card kpi">
                <div class="label">Inactivas</div>
                <div class="value">{{ number_format($totals->total - $totals->active) }}</div>
                <div class="foot">Fuera de operación</div>
            </div>
            <div class="card kpi">
                <div class="label">Área total</div>
                <div class="value">{{ number_format((float) $totals->area, 1, ',', '.') }} <small>ha</small></div>
                <div class="foot">Suma de las fincas con área registrada</div>
            </div>
        </div>
    @endif

    <div class="card">
        <form class="filters" method="GET" action="{{ route('farms.index') }}" role="search">
            <x-form.input name="q" label="Buscar" type="search" :value="$filters['q'] ?? null"
                          placeholder="Finca, vereda o municipio" maxlength="100" />
            @if ($manages)
                <x-form.select name="producer" label="Productor" placeholder="Todos" :options="$producers"
                               :value="$filters['producer'] ?? null" />
            @endif
            <x-form.select name="department" label="Departamento" placeholder="Todos"
                           :options="collect(config('aquasmart.departments'))->mapWithKeys(fn ($d) => [$d => $d])->all()"
                           :value="$filters['department'] ?? null" />
            <x-form.select name="status" label="Estado" placeholder="Todos"
                           :options="['active' => 'Activas', 'inactive' => 'Inactivas']"
                           :value="$filters['status'] ?? null" />
            <div class="btns">
                <button type="submit" class="btn">Filtrar</button>
                @if (array_filter($filters))
                    <a class="btn ghost" href="{{ route('farms.index') }}">Limpiar</a>
                @endif
            </div>
        </form>

        @if ($farms->isEmpty())
            <div class="empty">
                <div class="glyph"><x-icon name="farm" /></div>
                @if (array_filter($filters))
                    <h3>No hay fincas que coincidan</h3>
                    <p>Prueba con otro nombre, productor, departamento o estado.</p>
                @elseif ($manages)
                    <h3>Todavía no hay fincas</h3>
                    <p>Registra la primera finca de un productor para después crear sus estanques.</p>
                @else
                    <h3>No tienes fincas disponibles</h3>
                    <p>Cuando un administrador te asigne a una finca, aparecerá aquí.</p>
                @endif
            </div>
        @else
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">Finca</th>
                            <th scope="col">Productor</th>
                            <th scope="col">Ubicación</th>
                            <th scope="col" class="num">Área</th>
                            <th scope="col" class="num">Personal</th>
                            <th scope="col">Estado</th>
                            <th scope="col" class="num">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($farms as $farm)
                            <tr>
                                <td>
                                    <div class="who-cell">
                                        <span>
                                            <b><a class="link" style="color:inherit" href="{{ route('farms.show', $farm) }}">{{ $farm->name }}</a></b>
                                            <span class="mail">{{ $farm->location ?: 'Sin vereda registrada' }}</span>
                                        </span>
                                    </div>
                                </td>
                                <td>{{ $farm->producer->name }}</td>
                                <td>{{ $farm->municipality }}, {{ $farm->department }}</td>
                                <td class="num nowrap">{{ $farm->area_ha !== null ? number_format((float) $farm->area_ha, 2, ',', '.').' ha' : '—' }}</td>
                                <td class="num">{{ $farm->users_count }}</td>
                                <td><x-user-status :active="$farm->is_active" on="Activa" off="Inactiva" /></td>
                                <td>
                                    <div class="row-actions">
                                        @can('update', $farm)
                                            <a class="btn sm" href="{{ route('farms.edit', $farm) }}">Editar</a>
                                        @else
                                            <a class="btn sm" href="{{ route('farms.show', $farm) }}">Ver</a>
                                        @endcan
                                        @can('deactivate', $farm)
                                            <a class="btn sm" href="{{ route('farms.deactivate.confirm', $farm) }}">Desactivar</a>
                                        @endcan
                                        @can('activate', $farm)
                                            <form class="inline-form" method="POST" action="{{ route('farms.activate', $farm) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="btn sm">Activar</button>
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{ $farms->links() }}
        @endif
    </div>
@endsection
