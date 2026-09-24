@extends('layouts.guest')
@section('title', 'Recuperar contraseña — AquaSmart')

@section('content')
    <h1>Recuperar contraseña</h1>
    <p class="sub">Escribe tu correo y te enviaremos un enlace para crear una contraseña nueva.</p>

    <form method="POST" action="{{ route('password.email') }}" novalidate>
        @csrf

        <div class="field">
            <label for="email">Correo electrónico</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}"
                   @class(['input', 'is-invalid' => $errors->has('email')])
                   autocomplete="username" required autofocus
                   @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
            @error('email') <span class="error" id="email-error">{{ $message }}</span> @enderror
        </div>

        <button type="submit" class="btn primary block">Enviar enlace</button>
    </form>

    <p class="alt"><a class="link" href="{{ route('login') }}">Volver a iniciar sesión</a></p>
@endsection
