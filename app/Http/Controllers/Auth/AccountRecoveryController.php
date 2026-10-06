<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RequestAccountRecoveryRequest;
use App\Http\Requests\Auth\VerifyRecoveryCodeRequest;
use App\Http\Requests\Auth\VerifyRecoveryFileRequest;
use App\Http\Requests\Auth\VerifyRecoveryOtpRequest;
use App\Models\User;
use App\Services\AccountRecoveryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Recuperación de cuenta sin contraseña: correo → OTP/recovery code/archivo → nueva contraseña.
 * Nunca revela si un correo está registrado ni confía en IDs enviados por el cliente: la cuenta
 * que se está recuperando vive únicamente en la sesión del servidor (session('recovery.*')).
 */
class AccountRecoveryController extends Controller
{
    public function create(): View
    {
        return view('auth.recovery.request');
    }

    public function store(RequestAccountRecoveryRequest $request, AccountRecoveryService $service): RedirectResponse
    {
        $request->ensureIsNotRateLimited();
        $request->hit();

        $email = $request->string('email')->toString();
        $user = User::where('email', $email)->first();

        if ($user && $user->canAccessSystem()) {
            $service->sendOtp($user);
        }

        $request->session()->put('recovery.email', $email);

        // Misma respuesta exista o no la cuenta, para no revelar qué correos están registrados.
        return redirect()->route('recovery.verify')->with('status', __('auth.recovery_sent'));
    }

    public function verify(Request $request): View|RedirectResponse
    {
        $email = $request->session()->get('recovery.email');

        if (! $email) {
            return redirect()->route('recovery.request');
        }

        return view('auth.recovery.verify', [
            'email' => $email,
            'otpTtlMinutes' => AccountRecoveryService::OTP_TTL_MINUTES,
        ]);
    }

    public function resend(Request $request): RedirectResponse
    {
        $email = $request->session()->get('recovery.email');

        if (! $email) {
            return redirect()->route('recovery.request');
        }

        $key = 'recovery-resend|'.Str::transliterate(Str::lower($email)).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 3)) {
            return back()->withErrors(['otp' => __('auth.recovery_throttle', [
                'seconds' => RateLimiter::availableIn($key),
            ])]);
        }

        RateLimiter::hit($key, 900);

        $user = User::where('email', $email)->first();

        if ($user && $user->canAccessSystem()) {
            app(AccountRecoveryService::class)->sendOtp($user);
        }

        return back()->with('status', __('auth.recovery_sent'));
    }

    public function verifyOtp(VerifyRecoveryOtpRequest $request, AccountRecoveryService $service): RedirectResponse
    {
        $email = $request->session()->get('recovery.email');

        if (! $email) {
            return redirect()->route('recovery.request');
        }

        $user = User::where('email', $email)->first();

        if ($user && $user->canAccessSystem() && $service->verifyOtp($user, $request->string('otp')->toString())) {
            $this->grantRecoveryAccess($request, $user);

            return redirect()->route('recovery.password.edit');
        }

        return back()->withErrors(['otp' => __('auth.recovery_invalid')]);
    }

    public function verifyCode(VerifyRecoveryCodeRequest $request, AccountRecoveryService $service): RedirectResponse
    {
        $email = $request->session()->get('recovery.email');

        if (! $email) {
            return redirect()->route('recovery.request');
        }

        $request->ensureIsNotRateLimited($email);
        $request->hit($email);

        $user = User::where('email', $email)->first();

        if ($user && $user->canAccessSystem() && $service->verifyRecoveryCode($user, $request->string('code')->toString())) {
            $this->grantRecoveryAccess($request, $user);

            return redirect()->route('recovery.password.edit');
        }

        return back()->withErrors(['code' => __('auth.recovery_invalid')]);
    }

    public function verifyFile(VerifyRecoveryFileRequest $request, AccountRecoveryService $service): RedirectResponse
    {
        $email = $request->session()->get('recovery.email');

        if (! $email) {
            return redirect()->route('recovery.request');
        }

        $request->ensureIsNotRateLimited($email);
        $request->hit($email);

        $user = User::where('email', $email)->first();
        $token = trim($request->file('recovery_file')->get());

        if ($user && $user->canAccessSystem() && $service->verifyRecoveryFile($user, $token)) {
            $this->grantRecoveryAccess($request, $user);

            return redirect()->route('recovery.password.edit');
        }

        return back()->withErrors(['recovery_file' => __('auth.recovery_invalid')]);
    }

    private function grantRecoveryAccess(Request $request, User $user): void
    {
        $request->session()->forget('recovery.email');
        $request->session()->put('recovery.verified_user_id', $user->id);
        $request->session()->put('recovery.verified_at', now());
    }
}
