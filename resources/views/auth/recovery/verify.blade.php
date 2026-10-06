@extends('layouts.guest')
@section('title', 'Verificar identidad — AquaSmart')

@section('content')
    <h1>Verifica tu identidad</h1>
    <p class="sub">Enviamos un código a <strong>{{ $email }}</strong>.</p>

    @if (session('status'))
        <div class="notice ok" role="status">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('recovery.otp.verify') }}" novalidate>
        @csrf
        <div class="field">
            <label for="otp">Código de verificación</label>
            <input id="otp" name="otp" type="text" inputmode="numeric" maxlength="6" autocomplete="one-time-code"
                   @class(['input', 'is-invalid' => $errors->has('otp')])
                   required autofocus
                   @error('otp') aria-invalid="true" aria-describedby="otp-error" @enderror>
            @error('otp') <span class="error" id="otp-error">{{ $message }}</span> @enderror
            <span class="help">El código vence a los {{ $otpTtlMinutes }} minutos de haberse enviado y solo puede usarse una vez.</span>
        </div>
        <button type="submit" class="btn primary block">Verificar código</button>
    </form>

    <form method="POST" action="{{ route('recovery.otp.resend') }}" class="inline-form" style="display:block;margin-top:12px;text-align:center">
        @csrf
        <button type="submit" class="link" style="background:none;border:0;padding:0">¿No recibiste el código? Enviar uno nuevo</button>
    </form>

    <p class="alt"><a class="link" href="{{ route('recovery.request') }}">Usar otro correo</a> · <a class="link" href="{{ route('login') }}">Volver a iniciar sesión</a></p>
@endsection
