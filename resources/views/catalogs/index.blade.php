@extends('layouts.app')
@section('title', $catalog->label().' — AquaSmart')

@section('content')
    <div class="page-head">
        <div>
            <div class="breadcrumb">Catálogos / {{ $catalog->label() }}</div>
            <h1>{{ $catalog->label() }}</h1>
            <p>{{ $catalog->description() }}</p>
        </div>
        @can('create', $catalog->model())
            <div class="actions">
                <a class="btn primary" href="{{ route('catalogs.create', $catalog) }}">{{ $catalog->gender('Nuevo', 'Nueva') }} {{ $catalog->singular() }}</a>
            </div>
        @endcan
    </div>

    <nav class="chips tabs" aria-label="Catálogos">
        @foreach (App\Enums\Catalog::cases() as $case)
            <a class="chip" href="{{ route('catalogs.index', $case) }}" @if ($case === $catalog) aria-current="page" @endif>
                {{ $case->label() }} <span class="count">{{ $counts[$case->value] }}</span>
            </a>
        @endforeach
    </nav>

    <div class="card">
        <form class="filters" method="GET" action="{{ route('catalogs.index', $catalog) }}" role="search">
            <x-form.input name="q" label="Buscar" type="search" :value="$filters['q'] ?? null" maxlength="100" />
            <x-form.select name="status" label="Estado" placeholder="Todos"
                           :options="['active' => 'Activos', 'inactive' => 'Inactivos']" :value="$filters['status'] ?? null" />
            <div class="btns">
                <button type="submit" class="btn">Filtrar</button>
                @if (array_filter($filters))
                    <a class="btn ghost" href="{{ route('catalogs.index', $catalog) }}">Limpiar</a>
                @endif
            </div>
        </form>

        @if ($items->isEmpty())
            <div class="empty">
                <div class="glyph"><x-icon name="catalog" /></div>
                @if (array_filter($filters))
                    <h3>No hay resultados</h3>
                    <p>Prueba con otra búsqueda o estado.</p>
                @else
                    <h3>El catálogo está vacío</h3>
                    <p>Los registros de {{ Str::lower($catalog->label()) }} aparecerán aquí.</p>
                @endif
            </div>
        @else
            <div class="table-wrap">
                <table>
                    @include('catalogs.tables.'.$catalog->key())
                </table>
            </div>

            {{ $items->links() }}
        @endif
    </div>
@endsection
