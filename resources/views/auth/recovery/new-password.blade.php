@extends('layouts.guest')
@section('title', 'Nueva contraseña — AquaSmart')

@section('content')
    <h1>Crea una contraseña nueva</h1>
    <p class="sub">Debe tener al menos 8 caracteres, con letras y números.</p>

    <form method="POST" action="{{ route('recovery.password.update') }}" novalidate>
        @csrf

        <div class="field">
            <label for="password">Contraseña nueva</label>
            <input id="password" name="password" type="password"
                   @class(['input', 'is-invalid' => $errors->has('password')])
                   autocomplete="new-password" required autofocus
                   @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
            @error('password') <span class="error" id="password-error">{{ $message }}</span> @enderror
        </div>

        <div class="field">
            <label for="password_confirmation">Confirmar contraseña</label>
            <input id="password_confirmation" name="password_confirmation" type="password"
                   class="input" autocomplete="new-password" required>
        </div>

        <button type="submit" class="btn primary block">Guardar contraseña</button>
    </form>
@endsection
