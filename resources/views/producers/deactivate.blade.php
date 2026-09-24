@extends('layouts.app')
@section('title', 'Desactivar productor — AquaSmart')

@section('content')
    <div class="page-head">
        <div>
            <div class="breadcrumb">
                <a href="{{ route('producers.index') }}">Productores</a> /
                <a href="{{ route('producers.show', $producer) }}">{{ $producer->name }}</a> / Desactivar
            </div>
            <h1>Desactivar productor</h1>
        </div>
    </div>

    <div class="card confirm">
        <div class="head">
            <span class="sev crit" aria-hidden="true">!</span>
            <div>
                <h2>¿Desactivar a {{ $producer->name }}?</h2>
                <p style="margin:4px 0 0;color:var(--ink-3);font-size:13px">{{ $producer->documentLabel() }} · {{ $producer->municipality }}, {{ $producer->department }}</p>
            </div>
        </div>

        <ul>
            @if ($producer->users_count === 1)
                <li>Su cuenta de usuario perderá el acceso y se cerrarán sus sesiones abiertas.</li>
            @elseif ($producer->users_count > 1)
                <li>Sus {{ $producer->users_count }} cuentas de usuario perderán el acceso y se cerrarán sus sesiones abiertas.</li>
            @else
                <li>No tiene cuentas de usuario asociadas en este momento.</li>
            @endif
            <li>El productor no se elimina: su información y registros se conservan.</li>
            <li>Puedes volver a activarlo cuando quieras.</li>
        </ul>

        <form method="POST" action="{{ route('producers.deactivate', $producer) }}" class="form-actions">
            @csrf
            @method('PATCH')
            <a class="btn ghost" href="{{ route('producers.show', $producer) }}">Cancelar</a>
            <button type="submit" class="btn danger">Sí, desactivar</button>
        </form>
    </div>
@endsection
