<thead>
    <tr>
        <th scope="col">Alimento</th>
        <th scope="col" class="num">Proteína</th>
        <th scope="col" class="num">Pellet</th>
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
                <span class="sub-line">{{ $item->brand ?? 'Sin marca' }}</span>
            </td>
            <td class="num nowrap">{{ number_format((float) $item->protein_pct, 1, ',', '.') }} %</td>
            <td class="num nowrap">{{ $item->pellet_mm !== null ? number_format((float) $item->pellet_mm, 1, ',', '.').' mm' : '—' }}</td>
            <td><x-user-status :active="$item->is_active" /></td>
            @can('manage-catalogs')
                @include('catalogs._actions')
            @endcan
        </tr>
    @endforeach
</tbody>
