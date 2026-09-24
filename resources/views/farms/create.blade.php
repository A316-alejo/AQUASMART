@extends('layouts.app')
@section('title', 'Nueva finca — AquaSmart')

@section('content')
    <div class="page-head">
        <div>
            <div class="breadcrumb"><a href="{{ route('farms.index') }}">Fincas</a> / Nueva</div>
            <h1>Nueva finca</h1>
            <p>Registra una finca piscícola y asígnala a su productor.</p>
        </div>
    </div>

    @if (empty($producers))
        <div class="notice info" role="status">
            No hay productores activos. <a class="link" href="{{ route('producers.create') }}">Registra un productor</a> antes de crear fincas.
        </div>
    @endif

    <form class="card form-card" method="POST" action="{{ route('farms.store') }}" novalidate>
        @csrf

        @include('farms._form')

        <div class="form-actions">
            <a class="btn ghost" href="{{ route('farms.index') }}">Cancelar</a>
            <button type="submit" class="btn primary">Registrar finca</button>
        </div>
    </form>
@endsection
