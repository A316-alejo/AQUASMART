@extends('layouts.app')
@section('title', 'Cancelar lote — AquaSmart')

@section('content')
    <div class="page-head">
        <div>
            <div class="breadcrumb">
                <a href="{{ route('batches.index') }}">Lotes</a> /
                <a href="{{ route('batches.show', $batch) }}">{{ $batch->code }}</a> / Cancelar
            </div>
            <h1>Cancelar lote</h1>
        </div>
    </div>

    <div class="card confirm">
        <div class="head">
            <span class="sev crit" aria-hidden="true">!</span>
            <div>
                <h2>¿Cancelar el lote {{ $batch->code }}?</h2>
                <p style="margin:4px 0 0;color:var(--ink-3);font-size:13px">{{ $batch->name }} · {{ $batch->pond->code }} · {{ $batch->pond->farm->name }}</p>
            </div>
        </div>

        <ul>
            <li>Usa esta opción si el ciclo se interrumpió o el lote se registró por error.</li>
            <li>Se registra hoy como fecha de cierre.</li>
            <li>El lote no se elimina, pero ya no se podrá editar ni reactivar.</li>
        </ul>

        <form method="POST" action="{{ route('batches.cancel', $batch) }}" class="form-actions">
            @csrf
            @method('PATCH')
            <a class="btn ghost" href="{{ route('batches.show', $batch) }}">Volver</a>
            <button type="submit" class="btn danger">Sí, cancelar lote</button>
        </form>
    </div>
@endsection
