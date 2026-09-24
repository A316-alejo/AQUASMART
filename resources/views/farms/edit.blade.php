@extends('layouts.app')
@section('title', 'Editar finca — AquaSmart')

@section('content')
    <div class="page-head">
        <div>
            <div class="breadcrumb">
                <a href="{{ route('farms.index') }}">Fincas</a> /
                <a href="{{ route('farms.show', $farm) }}">{{ $farm->name }}</a> / Editar
            </div>
            <h1>Editar finca</h1>
            <p>{{ $farm->producer->name }}</p>
        </div>
        <div class="actions"><x-user-status :active="$farm->is_active" on="Activa" off="Inactiva" /></div>
    </div>

    <form class="card form-card" method="POST" action="{{ route('farms.update', $farm) }}" novalidate>
        @csrf
        @method('PUT')

        @include('farms._form')

        <div class="form-actions">
            <a class="btn ghost" href="{{ route('farms.show', $farm) }}">Cancelar</a>
            <button type="submit" class="btn primary">Guardar cambios</button>
        </div>
    </form>
@endsection
