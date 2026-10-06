@extends('layouts.app')
@section('title', 'Seguridad de la cuenta — AquaSmart')

@section('content')
    <div class="page-head">
        <div>
            <h1>Seguridad de la cuenta</h1>
            <p>Prepara aquí los métodos para recuperar tu cuenta si alguna vez pierdes acceso a tu correo.</p>
        </div>
    </div>

    @if (session('generated_codes'))
        <div class="card" style="margin-bottom:14px">
            <header><h3>Tus recovery codes nuevos</h3></header>
            <div class="notice warn">Guárdalos ahora en un lugar seguro. No volverán a mostrarse.</div>
            <div class="grid g4">
                @foreach (session('generated_codes') as $code)
                    <code class="key" style="font-size:15px">{{ $code }}</code>
                @endforeach
            </div>
        </div>
    @endif

    <div class="grid g2">
        <div class="card">
            <header><h3>Recovery codes</h3></header>
            <p class="muted-line">Códigos de un solo uso para recuperar tu cuenta si no puedes recibir el OTP por correo.</p>
            <p class="muted-line">Códigos sin usar: <strong>{{ $unusedCodesCount }}</strong></p>

            <form method="POST" action="{{ route('security.recovery.codes.generate') }}" style="margin-top:14px">
                @csrf
                <button type="submit" class="btn primary">
                    {{ $unusedCodesCount > 0 ? 'Generar códigos nuevos' : 'Generar recovery codes' }}
                </button>
            </form>
            @if ($unusedCodesCount > 0)
                <p class="muted-line" style="margin-top:8px">Generar códigos nuevos invalida los anteriores que no hayas usado.</p>
            @endif
        </div>

        <div class="card">
            <header><h3>Archivo de recuperación</h3></header>
            <p class="muted-line">Un archivo con un código secreto que puedes guardar y usar para recuperar tu cuenta.</p>
            <p class="muted-line">Estado: <span class="tag {{ $hasRecoveryFile ? 'ok' : 'mute' }}">{{ $hasRecoveryFile ? 'Activo' : 'No generado' }}</span></p>

            <form method="POST" action="{{ route('security.recovery.file.generate') }}" style="margin-top:14px">
                @csrf
                <button type="submit" class="btn primary">
                    {{ $hasRecoveryFile ? 'Generar archivo nuevo' : 'Generar archivo de recuperación' }}
                </button>
            </form>
            @if ($hasRecoveryFile)
                <p class="muted-line" style="margin-top:8px">Generar un archivo nuevo invalida el anterior.</p>
            @endif
        </div>
    </div>
@endsection
