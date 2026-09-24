@extends('layouts.app')
@section('title', 'Nuevo lote — AquaSmart')

@section('content')
    <div class="page-head">
        <div>
            <div class="breadcrumb"><a href="{{ route('batches.index') }}">Lotes</a> / Nuevo</div>
            <h1>Nuevo lote</h1>
            <p>Registra la siembra de un lote en un estanque activo.</p>
        </div>
    </div>

    @if (empty($ponds))
        <div class="notice info" role="status">
            No hay estanques activos en fincas activas. <a class="link" href="{{ route('ponds.create') }}">Registra un estanque</a> antes de crear lotes.
        </div>
    @endif
    @if (empty($species))
        <div class="notice info" role="status">
            No hay especies activas. <a class="link" href="{{ route('catalogs.index', App\Enums\Catalog::Species) }}">Revisa el catálogo de especies</a>.
        </div>
    @endif

    <form class="card form-card" method="POST" action="{{ route('batches.store') }}" novalidate>
        @csrf

        @include('batches._form')

        <div class="form-actions">
            <a class="btn ghost" href="{{ route('batches.index') }}">Cancelar</a>
            <button type="submit" class="btn primary">Registrar lote</button>
        </div>
    </form>
@endsection
