@extends('layouts.app')
@section('title', $producer->name.' — AquaSmart')

@section('content')
    <div class="page-head">
        <div>
            <div class="breadcrumb"><a href="{{ route('producers.index') }}">Productores</a> / Detalle</div>
            <h1>{{ $producer->name }}</h1>
            <p>{{ $producer->person_type->label() }} · {{ $producer->documentLabel() }}</p>
        </div>
        <div class="actions">
            <x-user-status :active="$producer->is_active" />
            @can('update', $producer)
                <a class="btn" href="{{ route('producers.edit', $producer) }}">Editar</a>
            @endcan
            @can('deactivate', $producer)
                <a class="btn" href="{{ route('producers.deactivate.confirm', $producer) }}">Desactivar</a>
            @endcan
            @can('activate', $producer)
                <form class="inline-form" method="POST" action="{{ route('producers.activate', $producer) }}">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="btn primary">Activar</button>
                </form>
            @endcan
        </div>
    </div>

    @unless ($producer->is_active)
        <div class="notice crit" role="status">
            Este productor está inactivo: las cuentas de usuario asociadas no pueden iniciar sesión.
        </div>
    @endunless

    <div class="grid g-2-1">
        <div class="card">
            <header><h3>Información del productor</h3></header>
            <dl class="def">
                <dt>Tipo de persona</dt>
                <dd>{{ $producer->person_type->label() }}</dd>
                <dt>Documento</dt>
                <dd>{{ $producer->document_type->label() }} · {{ $producer->document_number }}</dd>
                <dt>Teléfono</dt>
                <dd><a class="link" href="tel:{{ $producer->phone }}">{{ $producer->phone }}</a></dd>
                <dt>Correo electrónico</dt>
                <dd>
                    @if ($producer->email)
                        <a class="link" href="mailto:{{ $producer->email }}">{{ $producer->email }}</a>
                    @else
                        <span style="color:var(--ink-3)">Sin registrar</span>
                    @endif
                </dd>
                <dt>Ubicación</dt>
                <dd>{{ $producer->municipality }}, {{ $producer->department }}</dd>
                <dt>Registrado</dt>
                <dd>{{ $producer->created_at?->format('d/m/Y H:i') }}</dd>
                <dt>Última actualización</dt>
                <dd>{{ $producer->updated_at?->format('d/m/Y H:i') }}</dd>
            </dl>
        </div>

        <div class="grid" style="align-content:start">
            @if ($producer->relationLoaded('users'))
                <div class="card">
                    <header>
                        <h3>Cuentas de usuario</h3>
                        <span class="tag info plain">{{ $producer->users->count() }}</span>
                    </header>
                    @forelse ($producer->users as $user)
                        <div class="list-row">
                            <span class="avatar" aria-hidden="true">{{ $user->initials() }}</span>
                            <span class="body">
                                <b>{{ $user->name }}</b>
                                <span>{{ $user->role->label() }} · {{ $user->is_active ? 'Activo' : 'Inactivo' }}</span>
                            </span>
                            <a class="btn sm" href="{{ route('users.edit', $user) }}">Editar</a>
                        </div>
                    @empty
                        <p style="color:var(--ink-3);font-size:13px;margin:0">
                            Ninguna cuenta está asociada. Asócialas desde <a class="link" href="{{ route('users.index') }}">Usuarios</a>.
                        </p>
                    @endforelse
                </div>
            @endif

            <div class="card">
                <header><h3>Fincas</h3></header>
                <p style="color:var(--ink-3);font-size:13px;margin:0">
                    Las fincas de este productor aparecerán aquí cuando se habilite el módulo de Fincas.
                </p>
            </div>
        </div>
    </div>
@endsection
