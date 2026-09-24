@extends('layouts.app')
@section('title', 'Editar estanque — AquaSmart')

@section('content')
    <div class="page-head">
        <div>
            <div class="breadcrumb">
                <a href="{{ route('ponds.index') }}">Estanques</a> /
                <a href="{{ route('ponds.show', $pond) }}">{{ $pond->code }}</a> / Editar
            </div>
            <h1>Editar estanque</h1>
            <p>{{ $pond->farm->name }}</p>
        </div>
        <div class="actions"><x-user-status :active="$pond->is_active" /></div>
    </div>

    <form class="card form-card" method="POST" action="{{ route('ponds.update', $pond) }}" novalidate>
        @csrf
        @method('PUT')

        @include('ponds._form')

        <div class="form-actions">
            <a class="btn ghost" href="{{ route('ponds.show', $pond) }}">Cancelar</a>
            <button type="submit" class="btn primary">Guardar cambios</button>
        </div>
    </form>
@endsection
