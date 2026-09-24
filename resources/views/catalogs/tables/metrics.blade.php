<thead>
    <tr>
        <th scope="col">Parámetro</th>
        <th scope="col">Unidad</th>
        <th scope="col" class="num">Decimales</th>
        <th scope="col" class="num">Rango válido</th>
        <th scope="col">Estado</th>
        @can('manage-catalogs')
            <th scope="col" class="num">Acciones</th>
        @endcan
    </tr>
</thead>
<tbody>
    @foreach ($items as $item)
        <tr>
            <td>
                <b>{{ $item->name }}</b>
                <span class="sub-line"><code class="key">{{ $item->code }}</code></span>
            </td>
            <td>{{ $item->unit ?? '—' }}</td>
            <td class="num">{{ $item->decimals }}</td>
            <td class="num nowrap">
                @if ($item->min_value === null && $item->max_value === null)
                    Sin límite
                @else
                    {{ $item->format($item->min_value) }} a {{ $item->format($item->max_value) }}
                @endif
            </td>
            <td><x-user-status :active="$item->is_active" /></td>
            @can('manage-catalogs')
                @include('catalogs._actions')
            @endcan
        </tr>
    @endforeach
</tbody>
