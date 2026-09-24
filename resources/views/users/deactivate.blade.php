@extends('layouts.app')
@section('title', 'Desactivar usuario — AquaSmart')

@section('content')
    <div class="page-head">
        <div>
            <div class="breadcrumb"><a href="{{ route('users.index') }}">Usuarios</a> / Desactivar</div>
            <h1>Desactivar usuario</h1>
        </div>
    </div>

    <div class="card confirm">
        <div class="head">
            <span class="sev crit" aria-hidden="true">!</span>
            <div>
                <h2>¿Desactivar a {{ $user->name }}?</h2>
                <p style="margin:4px 0 0;color:var(--ink-3);font-size:13px">{{ $user->email }} · {{ $user->role->label() }}</p>
            </div>
        </div>

        <ul>
            <li>No podrá iniciar sesión en AquaSmart.</li>
            <li>Se cerrarán de inmediato todas sus sesiones abiertas.</li>
            <li>Sus datos y registros se conservan.</li>
            <li>Puedes volver a activar la cuenta cuando quieras.</li>
        </ul>

        <form method="POST" action="{{ route('users.deactivate', $user) }}" class="form-actions">
            @csrf
            @method('PATCH')
            <a class="btn ghost" href="{{ route('users.index') }}">Cancelar</a>
            <button type="submit" class="btn danger">Sí, desactivar</button>
        </form>
    </div>
@endsection
