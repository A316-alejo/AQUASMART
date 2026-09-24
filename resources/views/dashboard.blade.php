@extends('layouts.app')
@section('title', 'Dashboard general — AquaSmart')

@section('content')
    <div class="page-head">
        <div>
            <h1>Dashboard general</h1>
            <p>Hola, {{ auth()->user()->name }}. Aquí verás el estado de tus fincas y estanques.</p>
        </div>
    </div>

    <div class="card">
        <div class="empty">
            <div class="glyph"><x-icon name="pond" /></div>
            <h3>Todavía no hay datos para mostrar</h3>
            <p>Los indicadores de biomasa, calidad del agua y alertas aparecerán aquí cuando se registren fincas, estanques y lotes.</p>
        </div>
    </div>
@endsection
