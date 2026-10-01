@extends('layouts.app')
@section('title', 'Editar '.$device->code.' — AquaSmart')

@section('content')
    <div class="page-head">
        <div>
            <div class="breadcrumb">
                <a href="{{ route('devices.index') }}">Dispositivos</a> /
                <a href="{{ route('devices.show', $device) }}">{{ $device->code }}</a> / Editar
            </div>
            <h1>Editar dispositivo</h1>
        </div>
    </div>

    <form class="card form-card" method="POST" action="{{ route('devices.update', $device) }}" novalidate>
        @csrf
        @method('PUT')

        @include('devices._form')

        <div class="form-actions">
            <a class="btn ghost" href="{{ route('devices.show', $device) }}">Cancelar</a>
            <button type="submit" class="btn primary">Guardar cambios</button>
        </div>
    </form>
@endsection
