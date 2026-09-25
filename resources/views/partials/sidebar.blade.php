@php
    // Los módulos aparecen en el menú a medida que se registran sus rutas y según el permiso ('can') del usuario.
    $navigation = [
        'Operación' => [
            ['route' => 'dashboard', 'active' => 'dashboard', 'icon' => 'dashboard', 'label' => 'Dashboard general'],
            ['route' => 'producers.index', 'active' => 'producers.*', 'icon' => 'producers', 'label' => 'Productores', 'can' => ['viewAny', App\Models\Producer::class]],
            ['route' => 'farms.index', 'active' => 'farms.*', 'icon' => 'farm', 'label' => 'Fincas', 'can' => ['viewAny', App\Models\Farm::class]],
            ['route' => 'ponds.index', 'active' => 'ponds.*', 'icon' => 'pond', 'label' => 'Gestión de estanques', 'can' => ['viewAny', App\Models\Pond::class]],
            ['route' => 'batches.index', 'active' => 'batches.*', 'icon' => 'fish', 'label' => 'Lotes', 'can' => ['viewAny', App\Models\Batch::class]],
            ['route' => 'devices.index', 'active' => 'devices.*', 'icon' => 'iot', 'label' => 'Centro IoT'],
            ['route' => 'alerts.index', 'active' => 'alerts.*', 'icon' => 'alert', 'label' => 'Centro de alertas', 'can' => ['viewAny', App\Models\Alert::class]],
        ],
        'Análisis' => [
            ['route' => 'stats', 'active' => 'stats', 'icon' => 'stats', 'label' => 'Estadísticas'],
        ],
        'Administración' => [
            ['route' => 'users.index', 'active' => 'users.*', 'icon' => 'users', 'label' => 'Usuarios', 'can' => 'manage-users'],
            ['route' => 'catalogs.home', 'active' => 'catalogs.*', 'icon' => 'catalog', 'label' => 'Catálogos', 'can' => ['viewAny', App\Models\Species::class]],
        ],
    ];
@endphp
<aside class="side" id="app-sidebar">
    <x-brand />

    <nav aria-label="Navegación principal">
        @foreach ($navigation as $group => $items)
            @php
                $items = array_filter($items, fn ($item) => Route::has($item['route'])
                    && (! isset($item['can']) || Gate::allows(...(array) $item['can'])));
            @endphp
            @continue(empty($items))

            <div class="nav-group">{{ $group }}</div>
            @foreach ($items as $item)
                <a @class(['navlink', 'active' => request()->routeIs($item['active'])])
                   href="{{ route($item['route']) }}"
                   @if (request()->routeIs($item['active'])) aria-current="page" @endif>
                    <x-icon :name="$item['icon']" />
                    {{ $item['label'] }}
                </a>
            @endforeach
        @endforeach
    </nav>

    <div class="side-foot"><span class="live-dot"></span>Sincronizado {{ now()->format('H:i') }}</div>
</aside>
