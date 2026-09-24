@extends('layouts.app')
@section('title', 'Nuevo estanque — AquaSmart')

@section('content')
    <div class="page-head">
        <div>
            <div class="breadcrumb"><a href="{{ route('ponds.index') }}">Estanques</a> / Nuevo</div>
            <h1>Nuevo estanque</h1>
            <p>Registra un estanque o sistema de cultivo en una finca activa.</p>
        </div>
    </div>

    @if (empty($farms))
        <div class="notice info" role="status">
            No hay fincas activas. <a class="link" href="{{ route('farms.create') }}">Registra una finca</a> antes de crear estanques.
        </div>
    @endif

    <form class="card form-card" method="POST" action="{{ route('ponds.store') }}" novalidate>
        @csrf

        @include('ponds._form')

        <div class="form-actions">
            <a class="btn ghost" href="{{ route('ponds.index') }}">Cancelar</a>
            <button type="submit" class="btn primary">Registrar estanque</button>
        </div>
    </form>
@endsection
