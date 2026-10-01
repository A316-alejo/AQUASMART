@extends('layouts.app')
@section('title', 'Nuevo sensor — AquaSmart')

@section('content')
    <div class="page-head">
        <div>
            <div class="breadcrumb">
                <a href="{{ route('devices.index') }}">Dispositivos</a> /
                <a href="{{ route('devices.show', $device) }}">{{ $device->code }}</a> / Nuevo sensor
            </div>
            <h1>Nuevo sensor</h1>
            <p>Registra un sensor del dispositivo {{ $device->code }}.</p>
        </div>
    </div>

    @if (empty($ponds))
        <div class="notice info" role="status">
            La finca {{ $device->farm->name }} no tiene estanques activos. <a class="link" href="{{ route('ponds.create', ['farm' => $device->farm_id]) }}">Registra un estanque</a> antes de crear sensores.
        </div>
    @endif

    <form class="card form-card" method="POST" action="{{ route('devices.sensors.store', $device) }}" novalidate>
        @csrf

        @include('devices.sensors._form')

        <div class="form-actions">
            <a class="btn ghost" href="{{ route('devices.show', $device) }}">Cancelar</a>
            <button type="submit" class="btn primary">Registrar sensor</button>
        </div>
    </form>
@endsection
