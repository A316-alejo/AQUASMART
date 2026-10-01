@extends('layouts.app')
@section('title', 'Desactivar dispositivo — AquaSmart')

@section('content')
    <div class="page-head">
        <div>
            <div class="breadcrumb">
                <a href="{{ route('devices.index') }}">Dispositivos</a> /
                <a href="{{ route('devices.show', $device) }}">{{ $device->code }}</a> / Desactivar
            </div>
            <h1>Desactivar dispositivo</h1>
        </div>
    </div>

    <div class="card confirm">
        <div class="head">
            <span class="sev crit" aria-hidden="true">!</span>
            <div>
                <h2>¿Desactivar el dispositivo {{ $device->code }}?</h2>
                <p style="margin:4px 0 0;color:var(--ink-3);font-size:13px">{{ $device->name }} · {{ $device->farm->name }}</p>
            </div>
        </div>

        <ul>
            <li>El dispositivo quedará marcado como fuera de servicio.</li>
            <li>Su token dejará de autenticar mediciones en la API de ingestión de inmediato.</li>
            <li>El dispositivo y sus {{ $device->sensors_count }} sensor(es) no se eliminan: se conservan junto con las mediciones ya recibidas.</li>
            <li>Puedes volver a activarlo cuando lo necesites.</li>
        </ul>

        <form method="POST" action="{{ route('devices.deactivate', $device) }}" class="form-actions">
            @csrf
            @method('PATCH')
            <a class="btn ghost" href="{{ route('devices.show', $device) }}">Cancelar</a>
            <button type="submit" class="btn danger">Sí, desactivar</button>
        </form>
    </div>
@endsection
