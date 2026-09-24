@extends('layouts.app')
@section('title', 'Productores — AquaSmart')

@section('content')
    @php $manages = Gate::allows('manage-producers'); @endphp

    <div class="page-head">
        <div>
            <h1>Productores</h1>
            <p>{{ $manages ? 'Personas y empresas que producen en las fincas registradas en AquaSmart.' : 'Productor al que pertenece tu cuenta.' }}</p>
        </div>
        @can('create', App\Models\Producer::class)
            <div class="actions">
                <a class="btn primary" href="{{ route('producers.create') }}">Nuevo productor</a>
            </div>
        @endcan
    </div>

    @if ($totals)
        <div class="grid g4" style="margin-bottom:14px">
            <div class="card kpi">
                <div class="label">Productores registrados</div>
                <div class="value">{{ number_format($totals->total) }}</div>
                <div class="foot">Naturales y jurídicos</div>
            </div>
            <div class="card kpi">
                <div class="label">Activos</div>
                <div class="value">{{ number_format($totals->active) }}</div>
                <div class="foot">Operando en la plataforma</div>
            </div>
            <div class="card kpi">
                <div class="label">Inactivos</div>
                <div class="value">{{ number_format($totals->total - $totals->active) }}</div>
                <div class="foot">Sin acceso para sus usuarios</div>
            </div>
            <div class="card kpi">
                <div class="label">Personas jurídicas</div>
                <div class="value">{{ number_format($totals->juridical) }}</div>
                <div class="foot">Empresas y asociaciones</div>
            </div>
        </div>
    @endif

    <div class="card">
        @if ($manages)
            <form class="filters" method="GET" action="{{ route('producers.index') }}" role="search">
                <x-form.input name="q" label="Buscar" type="search" :value="$filters['q'] ?? null"
                              placeholder="Nombre, documento o teléfono" maxlength="100" />
                <x-form.select name="status" label="Estado" placeholder="Todos"
                               :options="['active' => 'Activos', 'inactive' => 'Inactivos']"
                               :value="$filters['status'] ?? null" />
                <div class="btns">
                    <button type="submit" class="btn">Filtrar</button>
                    @if (array_filter($filters))
                        <a class="btn ghost" href="{{ route('producers.index') }}">Limpiar</a>
                    @endif
                </div>
            </form>
        @endif

        @if ($producers->isEmpty())
            <div class="empty">
                <div class="glyph"><x-icon name="producers" /></div>
                @if (array_filter($filters))
                    <h3>No hay productores que coincidan</h3>
                    <p>Prueba con otro nombre, documento, teléfono o estado.</p>
                @else
                    <h3>Todavía no hay productores</h3>
                    <p>{{ $manages ? 'Registra el primer productor para después asociarle fincas y usuarios.' : 'Tu cuenta aún no está vinculada a un productor.' }}</p>
                @endif
            </div>
        @else
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">Productor</th>
                            <th scope="col">Documento y teléfono</th>
                            <th scope="col">Ubicación</th>
                            @if ($manages)
                                <th scope="col" class="num">Usuarios</th>
                            @endif
                            <th scope="col">Estado</th>
                            <th scope="col" class="num">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($producers as $producer)
                            <tr>
                                <td>
                                    <div class="who-cell">
                                        <span>
                                            <b><a class="link" style="color:inherit" href="{{ route('producers.show', $producer) }}">{{ $producer->name }}</a></b>
                                            <span class="mail">{{ $producer->person_type->label() }}</span>
                                        </span>
                                    </div>
                                </td>
                                <td class="nowrap">
                                    {{ $producer->documentLabel() }}
                                    <span class="mail" style="display:block;font-size:12.5px;color:var(--ink-3)">{{ $producer->phone }}</span>
                                </td>
                                <td>{{ $producer->municipality }}, {{ $producer->department }}</td>
                                @if ($manages)
                                    <td class="num">{{ $producer->users_count }}</td>
                                @endif
                                <td><x-user-status :active="$producer->is_active" /></td>
                                <td>
                                    <div class="row-actions">
                                        @can('update', $producer)
                                            <a class="btn sm" href="{{ route('producers.edit', $producer) }}">Editar</a>
                                        @else
                                            <a class="btn sm" href="{{ route('producers.show', $producer) }}">Ver</a>
                                        @endcan
                                        @can('deactivate', $producer)
                                            <a class="btn sm" href="{{ route('producers.deactivate.confirm', $producer) }}">Desactivar</a>
                                        @endcan
                                        @can('activate', $producer)
                                            <form class="inline-form" method="POST" action="{{ route('producers.activate', $producer) }}">
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

            {{ $producers->links() }}
        @endif
    </div>
@endsection
