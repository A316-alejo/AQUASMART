@extends('layouts.app')
@section('title', 'Dispositivos — AquaSmart')

@section('content')
    <div class="page-head">
        <div>
            <h1>Dispositivos</h1>
            <p>Dispositivos IoT (ESP32, gateways) que envían mediciones de calidad del agua a AquaSmart.</p>
        </div>
        @can('create', App\Models\Device::class)
            <div class="actions">
                <a class="btn primary" href="{{ route('devices.create') }}">Nuevo dispositivo</a>
            </div>
        @endcan
    </div>

    <div class="card">
        <form class="filters" method="GET" action="{{ route('devices.index') }}" role="search">
            <x-form.input name="q" label="Buscar" type="search" :value="$filters['q'] ?? null"
                          placeholder="Nombre o código" maxlength="100" />
            <x-form.select name="farm" label="Finca" placeholder="Todas" :options="$farms"
                           :value="$filters['farm'] ?? null" />
            <x-form.select name="type" label="Tipo" placeholder="Todos" :options="App\Enums\DeviceType::options()"
                           :value="$filters['type'] ?? null" />
            <x-form.select name="status" label="Estado" placeholder="Todos"
                           :options="['active' => 'Activos', 'inactive' => 'Inactivos']"
                           :value="$filters['status'] ?? null" />
            <div class="btns">
                <button type="submit" class="btn">Filtrar</button>
                @if (array_filter($filters))
                    <a class="btn ghost" href="{{ route('devices.index') }}">Limpiar</a>
                @endif
            </div>
        </form>

        @if ($devices->isEmpty())
            <div class="empty">
                <div class="glyph"><x-icon name="iot" /></div>
                @if (array_filter($filters))
                    <h3>No hay dispositivos que coincidan</h3>
                    <p>Prueba con otro nombre, finca, tipo o estado.</p>
                @elseif ($manages)
                    <h3>Todavía no hay dispositivos</h3>
                    <p>Registra el primer dispositivo de una finca para después agregarle sensores.</p>
                @else
                    <h3>No tienes dispositivos disponibles</h3>
                    <p>Cuando un administrador registre dispositivos en tus fincas, aparecerán aquí.</p>
                @endif
            </div>
        @else
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">Dispositivo</th>
                            <th scope="col">Finca</th>
                            <th scope="col">Tipo</th>
                            <th scope="col" class="num">Sensores</th>
                            <th scope="col">Última conexión</th>
                            <th scope="col">Estado</th>
                            <th scope="col" class="num">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($devices as $device)
                            <tr>
                                <td>
                                    <div class="who-cell">
                                        <span>
                                            <b><a class="link" style="color:inherit" href="{{ route('devices.show', $device) }}">{{ $device->code }}</a></b>
                                            <span class="mail">{{ $device->name }}</span>
                                        </span>
                                    </div>
                                </td>
                                <td>{{ $device->farm->name }}</td>
                                <td>{{ $device->type->label() }}</td>
                                <td class="num">{{ $device->sensors_count }}</td>
                                <td>{{ $device->last_connected_at?->format('d/m/Y H:i') ?? 'Nunca' }}</td>
                                <td><x-user-status :active="$device->is_active" on="Activo" off="Inactivo" /></td>
                                <td>
                                    <div class="row-actions">
                                        @can('update', $device)
                                            <a class="btn sm" href="{{ route('devices.edit', $device) }}">Editar</a>
                                        @else
                                            <a class="btn sm" href="{{ route('devices.show', $device) }}">Ver</a>
                                        @endcan
                                        @can('deactivate', $device)
                                            <a class="btn sm" href="{{ route('devices.deactivate.confirm', $device) }}">Desactivar</a>
                                        @endcan
                                        @can('activate', $device)
                                            <form class="inline-form" method="POST" action="{{ route('devices.activate', $device) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="btn sm">Activar</button>
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{ $devices->links() }}
        @endif
    </div>
@endsection
