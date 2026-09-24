@extends('layouts.app')
@section('title', 'Editar productor — AquaSmart')

@section('content')
    <div class="page-head">
        <div>
            <div class="breadcrumb">
                <a href="{{ route('producers.index') }}">Productores</a> /
                <a href="{{ route('producers.show', $producer) }}">{{ $producer->name }}</a> / Editar
            </div>
            <h1>Editar productor</h1>
            <p>{{ $producer->documentLabel() }}</p>
        </div>
        <div class="actions"><x-user-status :active="$producer->is_active" /></div>
    </div>

    <form class="card form-card" method="POST" action="{{ route('producers.update', $producer) }}" novalidate>
        @csrf
        @method('PUT')

        @include('producers._form')

        <div class="form-actions">
            <a class="btn ghost" href="{{ route('producers.show', $producer) }}">Cancelar</a>
            <button type="submit" class="btn primary">Guardar cambios</button>
        </div>
    </form>
@endsection
