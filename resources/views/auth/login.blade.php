@extends('layouts.guest')
@section('title', 'Iniciar sesión — AquaSmart')

@section('content')
    <h1>Iniciar sesión</h1>
    <p class="sub">Ingresa con la cuenta que te asignó el administrador.</p>

    <form method="POST" action="{{ route('login') }}" novalidate>
        @csrf

        <div class="field">
            <label for="email">Correo electrónico</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}"
                   @class(['input', 'is-invalid' => $errors->has('email')])
                   autocomplete="username" required autofocus
                   @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
            @error('email') <span class="error" id="email-error">{{ $message }}</span> @enderror
        </div>

        <div class="field">
            <label for="password">Contraseña</label>
            <input id="password" name="password" type="password"
                   @class(['input', 'is-invalid' => $errors->has('password')])
                   autocomplete="current-password" required
                   @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
            @error('password') <span class="error" id="password-error">{{ $message }}</span> @enderror
        </div>

        <div class="login-row">
            <label class="check">
                <input type="checkbox" name="remember" value="1" @checked(old('remember'))>
                Recordarme
            </label>
            <a class="link" href="{{ route('password.request') }}">¿Olvidaste tu contraseña?</a>
        </div>

        <button type="submit" class="btn primary block">Ingresar</button>
    </form>

    <p class="alt">¿No tienes cuenta? Solicítala al administrador de AquaSmart.</p>
@endsection
