<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head')
</head>
<body>
<div class="app">
    @include('partials.sidebar')
    <div class="scrim" data-nav-close></div>

    <div class="main">
        @include('partials.topbar')

        <main class="content">
            @include('partials.flash')

            @yield('content')
        </main>
    </div>
</div>
</body>
</html>
