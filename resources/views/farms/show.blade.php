@extends('layouts.app')
@section('title', $farm->name.' — AquaSmart')

@section('content')
    <div class="page-head">
        <div>
            <div class="breadcrumb"><a href="{{ route('farms.index') }}">Fincas</a> / Detalle</div>
            <h1>{{ $farm->name }}</h1>
            <p>
                @can('view', $farm->producer)
                    <a class="link" href="{{ route('producers.show', $farm->producer) }}">{{ $farm->producer->name }}</a>
                @else
                    {{ $farm->producer->name }}
                @endcan
                · {{ $farm->municipality }}, {{ $farm->department }}
            </p>
        </div>
        <div class="actions">
            <x-user-status :active="$farm->is_active" on="Activa" off="Inactiva" />
            @can('update', $farm)
                <a class="btn" href="{{ route('farms.edit', $farm) }}">Editar</a>
            @endcan
            @can('deactivate', $farm)
                <a class="btn" href="{{ route('farms.deactivate.confirm', $farm) }}">Desactivar</a>
            @endcan
            @can('activate', $farm)
                <form class="inline-form" method="POST" action="{{ route('farms.activate', $farm) }}">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="btn primary">Activar</button>
                </form>
            @endcan
        </div>
    </div>

    @unless ($farm->is_active)
        <div class="notice crit" role="status">Esta finca está inactiva: no está en operación.</div>
    @endunless

    <div class="grid g-2-1">
        <div class="grid" style="align-content:start">
            <div class="card">
                <header><h3>Información de la finca</h3></header>
                <dl class="def">
                    <dt>Productor</dt>
                    <dd>{{ $farm->producer->name }} · {{ $farm->producer->documentLabel() }}</dd>
                    <dt>Vereda o dirección</dt>
                    <dd>{{ $farm->location ?: 'Sin registrar' }}</dd>
                    <dt>Ubicación</dt>
                    <dd>{{ $farm->municipality }}, {{ $farm->department }}</dd>
                    <dt>Coordenadas</dt>
                    <dd>
                        @if ($farm->hasCoordinates())
                            {{ $farm->latitude }}, {{ $farm->longitude }}
                            · <a class="link" target="_blank" rel="noopener noreferrer"
                                 href="https://www.openstreetmap.org/?mlat={{ $farm->latitude }}&mlon={{ $farm->longitude }}#map=15/{{ $farm->latitude }}/{{ $farm->longitude }}">Ver en el mapa</a>
                        @else
                            <span style="color:var(--ink-3)">Sin registrar</span>
                        @endif
                    </dd>
                    <dt>Área</dt>
                    <dd>{{ $farm->area_ha !== null ? number_format((float) $farm->area_ha, 2, ',', '.').' ha' : 'Sin registrar' }}</dd>
                    <dt>Zona horaria</dt>
                    <dd>{{ $farm->timezone }}</dd>
                    <dt>Registrada</dt>
                    <dd>{{ $farm->created_at?->format('d/m/Y H:i') }}</dd>
                </dl>
            </div>

            <div class="card">
                <header>
                    <h3>Estanques</h3>
                    <span class="tag info plain">{{ $farm->ponds->count() }}</span>
                </header>
                @forelse ($farm->ponds as $pond)
                    <div class="list-row">
                        <span class="sev info" aria-hidden="true"><x-icon name="pond" style="width:18px;height:18px" /></span>
                        <span class="body">
                            <b>{{ $pond->code }} · {{ $pond->name }}</b>
                            <span>
                                {{ $pond->type->label() }}
                                @if ($pond->volume_m3 !== null) · {{ number_format((float) $pond->volume_m3, 0, ',', '.') }} m³ @endif
                                {{ $pond->is_active ? '' : '· Inactivo' }}
                            </span>
                        </span>
                        <a class="btn sm" href="{{ route('ponds.show', $pond) }}">Ver</a>
                    </div>
                @empty
                    <p style="color:var(--ink-3);font-size:13px;margin:0">Esta finca todavía no tiene estanques registrados.</p>
                @endforelse
                @can('create', App\Models\Pond::class)
                    @if ($farm->is_active)
                        <a class="btn block sm" style="margin-top:12px" href="{{ route('ponds.create', ['farm' => $farm->id]) }}">Nuevo estanque</a>
                    @endif
                @endcan
            </div>
        </div>

        <div class="card" style="align-self:start">
            <header>
                <h3>Personal asignado</h3>
                <span class="tag info plain">{{ $farm->users->count() }}</span>
            </header>

            @forelse ($farm->users as $member)
                <div class="list-row">
                    <span class="avatar" aria-hidden="true">{{ $member->initials() }}</span>
                    <span class="body">
                        <b>{{ $member->name }}</b>
                        <span>{{ $member->role->label() }}{{ $member->is_active ? '' : ' · Inactivo' }}</span>
                    </span>
                    @can('manageAssignments', $farm)
                        <form class="inline-form" method="POST" action="{{ route('farms.assignments.destroy', [$farm, $member]) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn sm" aria-label="Quitar a {{ $member->name }}">Quitar</button>
                        </form>
                    @endcan
                </div>
            @empty
                <p style="color:var(--ink-3);font-size:13px;margin:0 0 12px">Ningún técnico o usuario de consulta está asignado.</p>
            @endforelse

            @can('manageAssignments', $farm)
                @if ($assignableUsers->isNotEmpty())
                    <form method="POST" action="{{ route('farms.assignments.store', $farm) }}" style="margin-top:14px">
                        @csrf
                        <x-form.select name="user_id" label="Asignar técnico o usuario de consulta" placeholder="Selecciona una cuenta"
                                       :options="$assignableUsers->mapWithKeys(fn ($u) => [$u->id => $u->name.' · '.$u->role->label()])->all()"
                                       required />
                        <button type="submit" class="btn primary block">Asignar a la finca</button>
                    </form>
                @else
                    @error('user_id') <p class="notice crit">{{ $message }}</p> @enderror
                    <p style="color:var(--ink-3);font-size:12.5px;margin:12px 0 0">
                        No hay más cuentas disponibles. Solo se asignan técnicos o usuarios de consulta activos que no pertenezcan a otro productor.
                    </p>
                @endif
            @endcan
        </div>
    </div>
@endsection
