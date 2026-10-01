@extends('layouts.app')
@section('title', 'Nuevo dispositivo — AquaSmart')

@section('content')
    <div class="page-head">
        <div>
            <div class="breadcrumb"><a href="{{ route('devices.index') }}">Dispositivos</a> / Nuevo</div>
            <h1>Nuevo dispositivo</h1>
            <p>Registra un dispositivo IoT de una finca activa.</p>
        </div>
    </div>

    @if (empty($farms))
        <div class="notice info" role="status">
            No hay fincas activas. <a class="link" href="{{ route('farms.create') }}">Registra una finca</a> antes de crear dispositivos.
        </div>
    @endif

    <form class="card form-card" method="POST" action="{{ route('devices.store') }}" novalidate>
        @csrf

        @include('devices._form')

        <div class="form-actions">
            <a class="btn ghost" href="{{ route('devices.index') }}">Cancelar</a>
            <button type="submit" class="btn primary">Registrar dispositivo</button>
        </div>
    </form>
@endsection
