<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head')
</head>
<body>
<main class="error-page">
    <div class="card">
        <x-brand />
        <div class="code">@yield('code')</div>
        <h1>@yield('heading')</h1>
        <p>@yield('message')</p>
        <a class="btn primary" href="{{ auth()->check() ? route('dashboard') : route('login') }}">
            {{ auth()->check() ? 'Volver al dashboard' : 'Ir a iniciar sesión' }}
        </a>
    </div>
</main>
</body>
</html>
