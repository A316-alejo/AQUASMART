@extends('layouts.app')
@section('title', 'Desactivar finca — AquaSmart')

@section('content')
    <div class="page-head">
        <div>
            <div class="breadcrumb">
                <a href="{{ route('farms.index') }}">Fincas</a> /
                <a href="{{ route('farms.show', $farm) }}">{{ $farm->name }}</a> / Desactivar
            </div>
            <h1>Desactivar finca</h1>
        </div>
    </div>

    <div class="card confirm">
        <div class="head">
            <span class="sev crit" aria-hidden="true">!</span>
            <div>
                <h2>¿Desactivar {{ $farm->name }}?</h2>
                <p style="margin:4px 0 0;color:var(--ink-3);font-size:13px">{{ $farm->producer->name }} · {{ $farm->municipality }}, {{ $farm->department }}</p>
            </div>
        </div>

        <ul>
            <li>La finca quedará marcada como fuera de operación.</li>
            <li>La finca no se elimina: su información y registros se conservan.</li>
            @if ($farm->users_count > 0)
                <li>Las asignaciones de personal ({{ $farm->users_count }}) se mantienen.</li>
            @endif
            <li>Puedes volver a activarla cuando quieras.</li>
        </ul>

        <form method="POST" action="{{ route('farms.deactivate', $farm) }}" class="form-actions">
            @csrf
            @method('PATCH')
            <a class="btn ghost" href="{{ route('farms.show', $farm) }}">Cancelar</a>
            <button type="submit" class="btn danger">Sí, desactivar</button>
        </form>
    </div>
@endsection
