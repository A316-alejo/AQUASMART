@extends('layouts.app')
@section('title', 'Desactivar sensor — AquaSmart')

@section('content')
    <div class="page-head">
        <div>
            <div class="breadcrumb">
                <a href="{{ route('devices.index') }}">Dispositivos</a> /
                <a href="{{ route('devices.show', $device) }}">{{ $device->code }}</a> / {{ $sensor->code }} / Desactivar
            </div>
            <h1>Desactivar sensor</h1>
        </div>
    </div>

    <div class="card confirm">
        <div class="head">
            <span class="sev crit" aria-hidden="true">!</span>
            <div>
                <h2>¿Desactivar el sensor {{ $sensor->code }}?</h2>
                <p style="margin:4px 0 0;color:var(--ink-3);font-size:13px">{{ $sensor->name }} · estanque {{ $sensor->pond->code }}</p>
            </div>
        </div>

        <ul>
            <li>Las mediciones enviadas con este sensor dejarán de aceptarse en la API de ingestión.</li>
            <li>El sensor no se elimina: su historial de mediciones se conserva.</li>
            <li>Puedes volver a activarlo mientras el dispositivo esté activo.</li>
        </ul>

        <form method="POST" action="{{ route('devices.sensors.deactivate', [$device, $sensor]) }}" class="form-actions">
            @csrf
            @method('PATCH')
            <a class="btn ghost" href="{{ route('devices.show', $device) }}">Cancelar</a>
            <button type="submit" class="btn danger">Sí, desactivar</button>
        </form>
    </div>
@endsection
