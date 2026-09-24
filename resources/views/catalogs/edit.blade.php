@extends('layouts.app')
@section('title', 'Editar '.$catalog->singular().' — AquaSmart')

@section('content')
    <div class="page-head">
        <div>
            <div class="breadcrumb">
                Catálogos / <a href="{{ route('catalogs.index', $catalog) }}">{{ $catalog->label() }}</a> / Editar
            </div>
            <h1>Editar {{ $catalog->singular() }}</h1>
            <p>{{ $item->catalogLabel() }}</p>
        </div>
        <div class="actions">
            <x-user-status :active="$item->is_active" :on="$catalog->gender('Activo', 'Activa')" :off="$catalog->gender('Inactivo', 'Inactiva')" />
        </div>
    </div>

    <form class="card form-card" method="POST" action="{{ route('catalogs.update', [$catalog, $item]) }}" novalidate>
        @csrf
        @method('PUT')

        @include('catalogs.forms.'.$catalog->key())

        <div class="form-actions">
            <a class="btn ghost" href="{{ route('catalogs.index', $catalog) }}">Cancelar</a>
            <button type="submit" class="btn primary">Guardar cambios</button>
        </div>
    </form>
@endsection
