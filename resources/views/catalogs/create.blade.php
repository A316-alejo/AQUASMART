@extends('layouts.app')
@section('title', $catalog->gender('Nuevo', 'Nueva').' '.$catalog->singular().' — AquaSmart')

@section('content')
    <div class="page-head">
        <div>
            <div class="breadcrumb">
                Catálogos / <a href="{{ route('catalogs.index', $catalog) }}">{{ $catalog->label() }}</a> / {{ $catalog->gender('Nuevo', 'Nueva') }}
            </div>
            <h1>{{ $catalog->gender('Nuevo', 'Nueva') }} {{ $catalog->singular() }}</h1>
            <p>{{ $catalog->description() }}</p>
        </div>
    </div>

    <form class="card form-card" method="POST" action="{{ route('catalogs.store', $catalog) }}" novalidate>
        @csrf

        @include('catalogs.forms.'.$catalog->key())

        <div class="form-actions">
            <a class="btn ghost" href="{{ route('catalogs.index', $catalog) }}">Cancelar</a>
            <button type="submit" class="btn primary">Registrar {{ $catalog->singular() }}</button>
        </div>
    </form>
@endsection
