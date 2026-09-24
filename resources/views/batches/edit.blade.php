@extends('layouts.app')
@section('title', 'Editar lote — AquaSmart')

@section('content')
    <div class="page-head">
        <div>
            <div class="breadcrumb">
                <a href="{{ route('batches.index') }}">Lotes</a> /
                <a href="{{ route('batches.show', $batch) }}">{{ $batch->code }}</a> / Editar
            </div>
            <h1>Editar lote</h1>
            <p>{{ $batch->pond->farm->name }} · {{ $batch->pond->code }}</p>
        </div>
        <div class="actions"><x-batch-status :status="$batch->status" /></div>
    </div>

    <form class="card form-card" method="POST" action="{{ route('batches.update', $batch) }}" novalidate>
        @csrf
        @method('PUT')

        @include('batches._form')

        <div class="form-actions">
            <a class="btn ghost" href="{{ route('batches.show', $batch) }}">Cancelar</a>
            <button type="submit" class="btn primary">Guardar cambios</button>
        </div>
    </form>
@endsection
