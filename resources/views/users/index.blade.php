@extends('layouts.app')
@section('title', 'Usuarios — AquaSmart')

@section('content')
    <div class="page-head">
        <div>
            <h1>Usuarios</h1>
            <p>Administra quién accede a AquaSmart y qué puede hacer cada persona.</p>
        </div>
        @can('create', App\Models\User::class)
            <div class="actions">
                <a class="btn primary" href="{{ route('users.create') }}">Nuevo usuario</a>
            </div>
        @endcan
    </div>

    <div class="grid g4" style="margin-bottom:14px">
        <div class="card kpi">
            <div class="label">Usuarios registrados</div>
            <div class="value">{{ number_format($totals->total) }}</div>
            <div class="foot">Todas las cuentas</div>
        </div>
        <div class="card kpi">
            <div class="label">Activos</div>
            <div class="value">{{ number_format($totals->active) }}</div>
            <div class="foot">Pueden iniciar sesión</div>
        </div>
        <div class="card kpi">
            <div class="label">Inactivos</div>
            <div class="value">{{ number_format($totals->total - $totals->active) }}</div>
            <div class="foot">Acceso bloqueado</div>
        </div>
        <div class="card kpi">
            <div class="label">Administradores activos</div>
            <div class="value">{{ number_format($totals->admins) }}</div>
            <div class="foot">Con control total</div>
        </div>
    </div>

    <div class="card">
        <form class="filters" method="GET" action="{{ route('users.index') }}" role="search">
            <x-form.input name="q" label="Buscar" type="search" :value="$filters['q'] ?? null"
                          placeholder="Nombre o correo" maxlength="100" />
            <x-form.select name="role" label="Rol" placeholder="Todos"
                           :options="collect($roles)->mapWithKeys(fn ($role) => [$role->value => $role->label()])->all()"
                           :value="$filters['role'] ?? null" />
            <x-form.select name="status" label="Estado" placeholder="Todos"
                           :options="['active' => 'Activos', 'inactive' => 'Inactivos']"
                           :value="$filters['status'] ?? null" />
            <div class="btns">
                <button type="submit" class="btn">Filtrar</button>
                @if (array_filter($filters))
                    <a class="btn ghost" href="{{ route('users.index') }}">Limpiar</a>
                @endif
            </div>
        </form>

        @if ($users->isEmpty())
            <div class="empty">
                <div class="glyph"><x-icon name="users" /></div>
                <h3>No hay usuarios que coincidan</h3>
                <p>Prueba con otro nombre, rol o estado.</p>
            </div>
        @else
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">Usuario</th>
                            <th scope="col">Rol</th>
                            <th scope="col">Estado</th>
                            <th scope="col">Creado</th>
                            <th scope="col" class="num">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $user)
                            <tr>
                                <td>
                                    <div class="who-cell">
                                        <span class="avatar" aria-hidden="true">{{ $user->initials() }}</span>
                                        <span>
                                            <b>
                                                {{ $user->name }}
                                                @if ($user->is(auth()->user()))
                                                    <span class="tag info plain" style="margin-left:4px">Tú</span>
                                                @endif
                                            </b>
                                            <span class="mail">
                                                {{ $user->email }}
                                                @if ($user->producer)
                                                    · <a class="link" href="{{ route('producers.show', $user->producer) }}">{{ $user->producer->name }}</a>
                                                @endif
                                            </span>
                                        </span>
                                    </div>
                                </td>
                                <td><x-role-tag :role="$user->role" /></td>
                                <td><x-user-status :active="$user->is_active" /></td>
                                <td class="nowrap">{{ $user->created_at?->format('d/m/Y') }}</td>
                                <td>
                                    <div class="row-actions">
                                        @can('update', $user)
                                            <a class="btn sm" href="{{ route('users.edit', $user) }}">Editar</a>
                                        @endcan
                                        @can('deactivate', $user)
                                            <a class="btn sm" href="{{ route('users.deactivate.confirm', $user) }}">Desactivar</a>
                                        @endcan
                                        @can('activate', $user)
                                            <form class="inline-form" method="POST" action="{{ route('users.activate', $user) }}">
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

            {{ $users->links() }}
        @endif
    </div>
@endsection
