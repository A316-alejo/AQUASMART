<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head')
</head>
<body>
<div id="screen-login" class="on">
    <section class="login-art" aria-hidden="true">
        <x-brand />

        <div>
            <h2>Tu producción piscícola, bajo control</h2>
            <p>Monitorea la calidad del agua de tus estanques, registra el crecimiento de tus lotes y recibe alertas antes de que un problema afecte a tus peces.</p>
        </div>

        <div class="art-stats">
            <div><b>24/7</b><span>Monitoreo continuo</span></div>
            <div><b>pH · O₂ · °C</b><span>Parámetros del agua</span></div>
            <div><b>IoT</b><span>Listo para sensores</span></div>
        </div>

        <svg class="wave" viewBox="0 0 600 160" preserveAspectRatio="none">
            <path d="M0 80c75-40 150-40 225 0s150 40 225 0 100-30 150-10v90H0Z" fill="#fff" opacity=".35"/>
            <path d="M0 110c80-30 160-30 240 0s160 30 240 0 90-20 120-8v58H0Z" fill="#fff" opacity=".5"/>
        </svg>
    </section>

    <main class="login-form">
        <div class="login-box">
            <img class="logo" src="{{ asset('images/aquasmart-logo.png') }}" alt="AquaSmart Piscícola" width="420" height="389">

            @if (session('status'))
                <div class="notice ok" role="status">{{ session('status') }}</div>
            @endif

            @yield('content')
        </div>
    </main>
</div>
</body>
</html>
