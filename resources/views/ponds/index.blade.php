@extends('layouts.app')
@section('title', 'Gestión de estanques — AquaSmart')

@php
    $num = fn ($value, $unit) => $value !== null ? number_format((float) $value, 2, ',', '.').' '.$unit : '—';
@endphp

@section('content')
    <div class="page-head">
        <div>
            <h1>Gestión de estanques</h1>
            <p>{{ $totals ? 'Estanques y sistemas de cultivo de todas las fincas.' : 'Estanques de las fincas a las que tienes acceso.' }}</p>
        </div>
        @can('create', App\Models\Pond::class)
            <div class="actions">
                <a class="btn primary" href="{{ route('ponds.create') }}">Nuevo estanque</a>
            </div>
        @endcan
    </div>

    @if ($totals)
        <div class="grid g4" style="margin-bottom:14px">
            <div class="card kpi">
                <div class="label">Estanques registrados</div>
                <div class="value">{{ number_format($totals->total) }}</div>
                <div class="foot">En todas las fincas</div>
            </div>
            <div class="card kpi">
                <div class="label">Activos</div>
                <div class="value">{{ number_format($totals->active) }}</div>
                <div class="foot">En operación</div>
            </div>
            <div class="card kpi">
                <div class="label">Inactivos</div>
                <div class="value">{{ number_format($totals->total - $totals->active) }}</div>
                <div class="foot">Fuera de operación</div>
            </div>
            <div class="card kpi">
                <div class="label">Volumen total</div>
                <div class="value">{{ number_format((float) $totals->volume, 0, ',', '.') }} <small>m³</small></div>
                <div class="foot">Suma de los estanques con volumen registrado</div>
            </div>
        </div>
    @endif

    <div class="card">
        <form class="filters" method="GET" action="{{ route('ponds.index') }}" role="search">
            <x-form.input name="q" label="Buscar" type="search" :value="$filters['q'] ?? null"
                          placeholder="Código o nombre" maxlength="100" />
            <x-form.select name="farm" label="Finca" placeholder="Todas" :options="$farms" :value="$filters['farm'] ?? null" />
            <x-form.select name="type" label="Tipo" placeholder="Todos" :options="App\Enums\PondType::options()"
                           :value="$filters['type'] ?? null" />
            <x-form.select name="status" label="Estado" placeholder="Todos"
                           :options="['active' => 'Activos', 'inactive' => 'Inactivos']" :value="$filters['status'] ?? null" />
            <div class="btns">
                <button type="submit" class="btn">Filtrar</button>
                @if (array_filter($filters))
                    <a class="btn ghost" href="{{ route('ponds.index') }}">Limpiar</a>
                @endif
            </div>
        </form>

        @if ($ponds->isEmpty())
            <div class="empty">
                <div class="glyph"><x-icon name="pond" /></div>
                @if (array_filter($filters))
                    <h3>No hay estanques que coincidan</h3>
                    <p>Prueba con otro código, finca, tipo o estado.</p>
                @elseif ($totals)
                    <h3>Todavía no hay estanques</h3>
                    <p>Registra el primer estanque de una finca activa.</p>
                @else
                    <h3>No tienes estanques disponibles</h3>
                    <p>Los estanques de las fincas a las que tienes acceso aparecerán aquí.</p>
                @endif
            </div>
        @else
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">Estanque</th>
                            <th scope="col">Finca</th>
                            <th scope="col">Tipo</th>
                            <th scope="col" class="num">Dimensiones</th>
                            <th scope="col">Estado</th>
                            <th scope="col" class="num">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($ponds as $pond)
                            <tr>
                                <td>
                                    <div class="who-cell" style="min-width:170px">
                                        <span>
                                            <b><a class="link" style="color:inherit" href="{{ route('ponds.show', $pond) }}">{{ $pond->code }}</a></b>
                                            <span class="mail">{{ $pond->name }}</span>
                                        </span>
                                    </div>
                                </td>
                                <td style="min-width:170px">
                                    {{ $pond->farm->name }}
                                    <span style="display:block;font-size:12.5px;color:var(--ink-3)">{{ $pond->farm->producer->name }}</span>
                                </td>
                                <td>{{ $pond->type->label() }}</td>
                                <td class="num nowrap">
                                    {{ $num($pond->volume_m3, 'm³') }}
                                    <span style="display:block;font-size:12.5px;color:var(--ink-3)">{{ $num($pond->surface_m2, 'm²') }}</span>
                                </td>
                                <td><x-user-status :active="$pond->is_active" /></td>
                                <td>
                                    <div class="row-actions">
                                        @can('update', $pond)
                                            <a class="btn sm" href="{{ route('ponds.edit', $pond) }}">Editar</a>
                                        @else
                                            <a class="btn sm" href="{{ route('ponds.show', $pond) }}">Ver</a>
                                        @endcan
                                        @can('deactivate', $pond)
                                            <a class="btn sm" href="{{ route('ponds.deactivate.confirm', $pond) }}">Desactivar</a>
                                        @endcan
                                        @can('activate', $pond)
                                            <form class="inline-form" method="POST" action="{{ route('ponds.activate', $pond) }}">
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

            {{ $ponds->links() }}
        @endif
    </div>
@endsection
