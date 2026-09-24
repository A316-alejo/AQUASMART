@extends('layouts.app')
@section('title', 'Editar usuario — AquaSmart')

@section('content')
    <div class="page-head">
        <div>
            <div class="breadcrumb"><a href="{{ route('users.index') }}">Usuarios</a> / Editar</div>
            <h1>{{ $user->name }}</h1>
            <p>{{ $user->email }} · Creado el {{ $user->created_at?->format('d/m/Y') }}</p>
        </div>
        <div class="actions">
            <x-role-tag :role="$user->role" />
            <x-user-status :active="$user->is_active" />
        </div>
    </div>

    <form class="card form-card" method="POST" action="{{ route('users.update', $user) }}" novalidate style="margin-bottom:14px">
        @csrf
        @method('PUT')

        @include('users._form')

        <div class="form-actions">
            <a class="btn ghost" href="{{ route('users.index') }}">Cancelar</a>
            <button type="submit" class="btn primary">Guardar cambios</button>
        </div>
    </form>

    @canany(['deactivate', 'activate', 'resetPassword'], $user)
        <div class="card form-card">
            <header><h3>Estado y seguridad</h3></header>

            @can('resetPassword', $user)
                <div class="list-row">
                    <span class="body">
                        <b>Enlace para restablecer la contraseña</b>
                        <span>Envía un correo a {{ $user->email }} para que la persona cree una contraseña nueva.</span>
                    </span>
                    <form class="inline-form" method="POST" action="{{ route('users.password-link', $user) }}">
                        @csrf
                        <button type="submit" class="btn sm">Enviar enlace</button>
                    </form>
                </div>
            @endcan

            @can('deactivate', $user)
                <div class="list-row">
                    <span class="body">
                        <b>Desactivar cuenta</b>
                        <span>La persona no podrá iniciar sesión y se cerrarán sus sesiones abiertas.</span>
                    </span>
                    <a class="btn sm" href="{{ route('users.deactivate.confirm', $user) }}">Desactivar</a>
                </div>
            @endcan

            @can('activate', $user)
                <div class="list-row">
                    <span class="body">
                        <b>Activar cuenta</b>
                        <span>La persona podrá volver a iniciar sesión con su contraseña.</span>
                    </span>
                    <form class="inline-form" method="POST" action="{{ route('users.activate', $user) }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="btn sm primary">Activar</button>
                    </form>
                </div>
            @endcan
        </div>
    @endcanany
@endsection
