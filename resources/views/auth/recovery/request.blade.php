@extends('layouts.guest')
@section('title', 'Recuperar cuenta — AquaSmart')

@section('content')
    <h1>Recuperar tu cuenta</h1>
    <p class="sub">Escribe tu correo. Si la cuenta existe, te enviaremos un código de verificación.</p>

    @if ($errors->any() && $errors->has('email'))
        <div class="notice crit" role="alert">{{ $errors->first('email') }}</div>
    @endif

    <form method="POST" action="{{ route('recovery.send') }}" novalidate>
        @csrf

        <div class="field">
            <label for="email">Correo electrónico</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}"
                   @class(['input', 'is-invalid' => $errors->has('email')])
                   autocomplete="username" required autofocus
                   @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
            @error('email') <span class="error" id="email-error">{{ $message }}</span> @enderror
        </div>

        <button type="submit" class="btn primary block">Enviar código</button>
    </form>

    <p class="alt"><a class="link" href="{{ route('login') }}">Volver a iniciar sesión</a></p>
@endsection
