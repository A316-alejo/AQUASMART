@php $user = auth()->user(); @endphp
<header class="topbar">
    <button type="button" class="burger" data-nav-toggle aria-controls="app-sidebar" aria-label="Abrir menú">
        <x-icon name="menu" />
    </button>

    <div class="right">
        <span class="user-chip">
            <span class="avatar" aria-hidden="true">{{ $user->initials() }}</span>
            <span class="who"><b>{{ $user->name }}</b><span>{{ $user->role->label() }}</span></span>
        </span>

        <a class="icon-btn" href="{{ route('security.recovery.show') }}" title="Seguridad de la cuenta">
            <x-icon name="shield" />
            <span class="sr-only">Seguridad de la cuenta</span>
        </a>

        <form class="logout" method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="icon-btn" title="Cerrar sesión">
                <x-icon name="logout" />
                <span class="sr-only">Cerrar sesión</span>
            </button>
        </form>
    </div>
</header>
