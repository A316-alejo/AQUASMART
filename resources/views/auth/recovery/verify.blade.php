@extends('layouts.guest')
@section('title', 'Verificar identidad — AquaSmart')

@section('content')
    <h1>Verifica tu identidad</h1>
    <p class="sub">Enviamos un código a <strong>{{ $email }}</strong>. También puedes usar un código de recuperación o tu archivo de recuperación.</p>

    @if (session('status'))
        <div class="notice ok" role="status">{{ session('status') }}</div>
    @endif

    <div class="method-tabs">
        <input type="radio" name="method" id="m-otp" class="method-tabs__radio"
               @checked(! $errors->has('code') && ! $errors->has('recovery_file'))>
        <input type="radio" name="method" id="m-code" class="method-tabs__radio" @checked($errors->has('code'))>
        <input type="radio" name="method" id="m-file" class="method-tabs__radio" @checked($errors->has('recovery_file'))>

        <div class="chips tabs method-tabs__nav">
            <label for="m-otp" class="chip">Código por correo</label>
            <label for="m-code" class="chip">Código de recuperación</label>
            <label for="m-file" class="chip">Archivo de recuperación</label>
        </div>

        <div class="method-tabs__panel" data-panel="otp">
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
        </div>

        <div class="method-tabs__panel" data-panel="code">
            <form method="POST" action="{{ route('recovery.code.verify') }}" novalidate>
                @csrf
                <div class="field">
                    <label for="code">Código de recuperación</label>
                    <input id="code" name="code" type="text" placeholder="0000-0000" maxlength="9" autocomplete="off"
                           @class(['input', 'is-invalid' => $errors->has('code')])
                           required
                           @error('code') aria-invalid="true" aria-describedby="code-error" @enderror>
                    @error('code') <span class="error" id="code-error">{{ $message }}</span> @enderror
                    <span class="help">Es uno de los códigos que generaste antes desde "Seguridad de la cuenta". Cada código solo se puede usar una vez.</span>
                </div>
                <button type="submit" class="btn primary block">Verificar código</button>
            </form>
        </div>

        <div class="method-tabs__panel" data-panel="file">
            <form method="POST" action="{{ route('recovery.file.verify') }}" enctype="multipart/form-data" novalidate>
                @csrf
                <div class="field">
                    <label for="recovery_file">Archivo de recuperación</label>
                    <input id="recovery_file" name="recovery_file" type="file" accept=".txt"
                           @class(['input', 'is-invalid' => $errors->has('recovery_file')])
                           required
                           @error('recovery_file') aria-invalid="true" aria-describedby="recovery_file-error" @enderror>
                    @error('recovery_file') <span class="error" id="recovery_file-error">{{ $message }}</span> @enderror
                    <span class="help">El archivo .txt que descargaste antes desde "Seguridad de la cuenta". Se invalida después de usarse.</span>
                </div>
                <button type="submit" class="btn primary block">Verificar archivo</button>
            </form>
        </div>
    </div>

    <p class="alt"><a class="link" href="{{ route('recovery.request') }}">Usar otro correo</a> · <a class="link" href="{{ route('login') }}">Volver a iniciar sesión</a></p>
@endsection
