@php /** @var \Illuminate\Database\Eloquent\Model $item */ @endphp
<td>
    <div class="row-actions">
        @can('update', $item)
            <a class="btn sm" href="{{ route('catalogs.edit', [$catalog, $item]) }}">Editar</a>
        @endcan
        @can('deactivate', $item)
            <a class="btn sm" href="{{ route('catalogs.deactivate.confirm', [$catalog, $item]) }}">Desactivar</a>
        @endcan
        @can('activate', $item)
            <form class="inline-form" method="POST" action="{{ route('catalogs.activate', [$catalog, $item]) }}">
                @csrf
                @method('PATCH')
                <button type="submit" class="btn sm">Activar</button>
            </form>
        @endcan
    </div>
</td>
