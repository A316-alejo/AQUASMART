@extends('layouts.app')
@section('title', $device->code.' — AquaSmart')

@section('content')
    <div class="page-head">
        <div>
            <div class="breadcrumb"><a href="{{ route('devices.index') }}">Dispositivos</a> / Detalle</div>
            <h1>{{ $device->code }}</h1>
            <p>{{ $device->name }} · {{ $device->farm->name }}</p>
        </div>
        <div class="actions">
            <x-user-status :active="$device->is_active" on="Activo" off="Inactivo" />
            @can('update', $device)
                <a class="btn" href="{{ route('devices.edit', $device) }}">Editar</a>
            @endcan
            @can('deactivate', $device)
                <a class="btn" href="{{ route('devices.deactivate.confirm', $device) }}">Desactivar</a>
            @endcan
            @can('activate', $device)
                <form class="inline-form" method="POST" action="{{ route('devices.activate', $device) }}">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="btn primary">Activar</button>
                </form>
            @endcan
        </div>
    </div>

    @unless ($device->is_active)
        <div class="notice crit" role="status">Este dispositivo está inactivo: su token no puede autenticar mediciones.</div>
    @endunless

    @if (session('device_token'))
        <div class="notice ok" role="status">
            <b>Token del dispositivo (solo se muestra una vez):</b>
            <div style="margin-top:6px"><code class="key" style="font-size:13px">{{ session('device_token') }}</code></div>
            <p style="margin:8px 0 0;font-size:12.5px">Guárdalo ahora: no se volverá a mostrar. Configúralo en el dispositivo como cabecera <code class="key">Authorization: Bearer …</code>.</p>
        </div>
    @endif

    <div class="grid g-2-1">
        <div class="grid" style="align-content:start">
            <div class="card">
                <header><h3>Información del dispositivo</h3></header>
                <dl class="def">
                    <dt>Finca</dt>
                    <dd>{{ $device->farm->name }} · {{ $device->farm->producer->name }}</dd>
                    <dt>Tipo</dt>
                    <dd>{{ $device->type->label() }}</dd>
                    <dt>Última conexión</dt>
                    <dd>{{ $device->last_connected_at?->format('d/m/Y H:i') ?? 'Nunca se ha conectado' }}</dd>
                    <dt>Registrado</dt>
                    <dd>{{ $device->created_at?->format('d/m/Y H:i') }}</dd>
                </dl>

                @can('regenerateToken', $device)
                    <form class="inline-form" method="POST" action="{{ route('devices.token.regenerate', $device) }}"
                          style="margin-top:12px" onsubmit="return confirm('¿Generar un nuevo token? El token actual dejará de funcionar de inmediato.')">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="btn block sm">Regenerar token</button>
                    </form>
                @endcan
            </div>
        </div>

        <div class="card" style="align-self:start">
            <header>
                <h3>Sensores</h3>
                <span class="tag info plain">{{ $device->sensors->count() }}</span>
            </header>
            @forelse ($device->sensors as $sensor)
                <div class="list-row">
                    <span class="sev info" aria-hidden="true"><x-icon name="iot" style="width:18px;height:18px" /></span>
                    <span class="body">
                        <b>{{ $sensor->code }} · {{ $sensor->name }}</b>
                        <span>
                            {{ $sensor->metric->name }} ({{ $sensor->metric->unit }}) · estanque {{ $sensor->pond->code }}
                            {{ $sensor->is_active ? '' : '· Inactivo' }}
                        </span>
                    </span>
                    @can('update', $sensor)
                        <a class="btn sm" href="{{ route('devices.sensors.edit', [$device, $sensor]) }}">Editar</a>
                    @endcan
                    @can('deactivate', $sensor)
                        <a class="btn sm" href="{{ route('devices.sensors.deactivate.confirm', [$device, $sensor]) }}">Desactivar</a>
                    @endcan
                    @can('activate', $sensor)
                        <form class="inline-form" method="POST" action="{{ route('devices.sensors.activate', [$device, $sensor]) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="btn sm">Activar</button>
                        </form>
                    @endcan
                </div>
            @empty
                <p style="color:var(--ink-3);font-size:13px;margin:0">Este dispositivo todavía no tiene sensores registrados.</p>
            @endforelse

            @can('create', App\Models\Sensor::class)
                @if ($device->is_active)
                    <a class="btn block sm" style="margin-top:12px" href="{{ route('devices.sensors.create', $device) }}">Nuevo sensor</a>
                @endif
            @endcan
        </div>
    </div>
@endsection
