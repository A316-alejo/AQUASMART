@extends('layouts.app')
@section('title', 'Desactivar '.$catalog->singular().' — AquaSmart')

@section('content')
    <div class="page-head">
        <div>
            <div class="breadcrumb">
                Catálogos / <a href="{{ route('catalogs.index', $catalog) }}">{{ $catalog->label() }}</a> / Desactivar
            </div>
            <h1>Desactivar {{ $catalog->singular() }}</h1>
        </div>
    </div>

    <div class="card confirm">
        <div class="head">
            <span class="sev crit" aria-hidden="true">!</span>
            <div>
                <h2>¿Desactivar {{ $catalog->gender('el', 'la') }} {{ $catalog->singular() }} {{ $item->catalogLabel() }}?</h2>
                <p style="margin:4px 0 0;color:var(--ink-3);font-size:13px">{{ $catalog->label() }}</p>
            </div>
        </div>

        <ul>
            <li>Dejará de aparecer como opción en los registros nuevos.</li>
            <li>No se elimina: los registros que ya {{ $catalog->gender('lo', 'la') }} usan se conservan.</li>
            <li>Puedes volver a {{ $catalog->gender('activarlo', 'activarla') }} en cualquier momento.</li>
        </ul>

        <form method="POST" action="{{ route('catalogs.deactivate', [$catalog, $item]) }}" class="form-actions">
            @csrf
            @method('PATCH')
            <a class="btn ghost" href="{{ route('catalogs.index', $catalog) }}">Cancelar</a>
            <button type="submit" class="btn danger">Sí, desactivar</button>
        </form>
    </div>
@endsection
