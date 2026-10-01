@extends('layouts.app')
@section('title', 'Editar sensor — AquaSmart')

@section('content')
    <div class="page-head">
        <div>
            <div class="breadcrumb">
                <a href="{{ route('devices.index') }}">Dispositivos</a> /
                <a href="{{ route('devices.show', $device) }}">{{ $device->code }}</a> / {{ $sensor->code }} / Editar
            </div>
            <h1>Editar sensor</h1>
        </div>
    </div>

    <form class="card form-card" method="POST" action="{{ route('devices.sensors.update', [$device, $sensor]) }}" novalidate>
        @csrf
        @method('PUT')

        @include('devices.sensors._form')

        <div class="form-actions">
            <a class="btn ghost" href="{{ route('devices.show', $device) }}">Cancelar</a>
            <button type="submit" class="btn primary">Guardar cambios</button>
        </div>
    </form>
@endsection
