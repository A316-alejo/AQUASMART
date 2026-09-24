@extends('layouts.app')
@section('title', 'Nuevo productor — AquaSmart')

@section('content')
    <div class="page-head">
        <div>
            <div class="breadcrumb"><a href="{{ route('producers.index') }}">Productores</a> / Nuevo</div>
            <h1>Nuevo productor</h1>
            <p>Registra a la persona o empresa. Después podrás asociarle fincas y cuentas de usuario.</p>
        </div>
    </div>

    <form class="card form-card" method="POST" action="{{ route('producers.store') }}" novalidate>
        @csrf

        @include('producers._form')

        <div class="form-actions">
            <a class="btn ghost" href="{{ route('producers.index') }}">Cancelar</a>
            <button type="submit" class="btn primary">Registrar productor</button>
        </div>
    </form>
@endsection
