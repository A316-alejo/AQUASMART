@extends('layouts.app')
@section('title', 'Cerrar lote — AquaSmart')

@section('content')
    <div class="page-head">
        <div>
            <div class="breadcrumb">
                <a href="{{ route('batches.index') }}">Lotes</a> /
                <a href="{{ route('batches.show', $batch) }}">{{ $batch->code }}</a> / Cerrar
            </div>
            <h1>Cerrar lote</h1>
        </div>
    </div>

    <div class="card confirm">
        <div class="head">
            <span class="sev info" aria-hidden="true">✓</span>
            <div>
                <h2>¿Cerrar el lote {{ $batch->code }}?</h2>
                <p style="margin:4px 0 0;color:var(--ink-3);font-size:13px">{{ $batch->name }} · {{ $batch->pond->code }} · {{ $batch->pond->farm->name }}</p>
            </div>
        </div>

        <ul>
            <li>El ciclo de producción termina en la fecha de cierre (por ejemplo, la cosecha).</li>
            <li>El lote no se elimina: queda como registro histórico del estanque.</li>
            <li>Un lote cerrado no se puede editar ni reabrir.</li>
        </ul>

        <form method="POST" action="{{ route('batches.close', $batch) }}" novalidate>
            @csrf
            @method('PATCH')
            <x-form.input name="closed_at" label="Fecha de cierre" type="date" :value="today()->toDateString()"
                          min="{{ $batch->stocked_at->toDateString() }}" max="{{ today()->toDateString() }}" required
                          help="Sembrado el {{ $batch->stocked_at->format('d/m/Y') }}." />
            <div class="form-actions">
                <a class="btn ghost" href="{{ route('batches.show', $batch) }}">Volver</a>
                <button type="submit" class="btn primary">Sí, cerrar lote</button>
            </div>
        </form>
    </div>
@endsection
