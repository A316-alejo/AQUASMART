@extends('layouts.app')
@section('title', 'Nuevo usuario — AquaSmart')

@section('content')
    <div class="page-head">
        <div>
            <div class="breadcrumb"><a href="{{ route('users.index') }}">Usuarios</a> / Nuevo</div>
            <h1>Nuevo usuario</h1>
            <p>Crea una cuenta y asígnale el rol que le corresponde.</p>
        </div>
    </div>

    <form class="card form-card" method="POST" action="{{ route('users.store') }}" novalidate>
        @csrf

        @include('users._form')

        <div class="form-actions">
            <a class="btn ghost" href="{{ route('users.index') }}">Cancelar</a>
            <button type="submit" class="btn primary">Crear usuario</button>
        </div>
    </form>
@endsection
