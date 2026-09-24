<thead>
    <tr>
        <th scope="col">Especie</th>
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
                <b>{{ $item->common_name }}</b>
                <span class="sub-line"><i>{{ $item->scientific_name ?? 'Sin nombre científico' }}</i></span>
            </td>
            <td><x-user-status :active="$item->is_active" on="Activa" off="Inactiva" /></td>
            @can('manage-catalogs')
                @include('catalogs._actions')
            @endcan
        </tr>
    @endforeach
</tbody>
