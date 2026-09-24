@extends('layouts.app')
@section('title', 'Desactivar estanque — AquaSmart')

@section('content')
    <div class="page-head">
        <div>
            <div class="breadcrumb">
                <a href="{{ route('ponds.index') }}">Estanques</a> /
                <a href="{{ route('ponds.show', $pond) }}">{{ $pond->code }}</a> / Desactivar
            </div>
            <h1>Desactivar estanque</h1>
        </div>
    </div>

    <div class="card confirm">
        <div class="head">
            <span class="sev crit" aria-hidden="true">!</span>
            <div>
                <h2>¿Desactivar el estanque {{ $pond->code }}?</h2>
                <p style="margin:4px 0 0;color:var(--ink-3);font-size:13px">{{ $pond->name }} · {{ $pond->farm->name }}</p>
            </div>
        </div>

        <ul>
            <li>El estanque quedará marcado como fuera de operación.</li>
            <li>El estanque no se elimina: su información y registros se conservan.</li>
            <li>Puedes volver a activarlo mientras su finca esté activa.</li>
        </ul>

        <form method="POST" action="{{ route('ponds.deactivate', $pond) }}" class="form-actions">
            @csrf
            @method('PATCH')
            <a class="btn ghost" href="{{ route('ponds.show', $pond) }}">Cancelar</a>
            <button type="submit" class="btn danger">Sí, desactivar</button>
        </form>
    </div>
@endsection
