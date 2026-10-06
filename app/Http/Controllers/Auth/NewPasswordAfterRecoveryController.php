<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SetRecoveredPasswordRequest;
use App\Models\User;
use App\Services\AccountRecoveryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NewPasswordAfterRecoveryController extends Controller
{
    public function edit(Request $request): View|RedirectResponse
    {
        if (! $this->verifiedUser($request)) {
            return redirect()->route('recovery.request');
        }

        return view('auth.recovery.new-password');
    }

    public function update(SetRecoveredPasswordRequest $request, AccountRecoveryService $service): RedirectResponse
    {
        $user = $this->verifiedUser($request);

        if (! $user) {
            return redirect()->route('recovery.request');
        }

        $service->resetPassword($user, $request->string('password')->toString());

        $request->session()->forget(['recovery.verified_user_id', 'recovery.verified_at']);
        $request->session()->regenerate();

        return redirect()->route('login')->with('status', __('auth.recovery_password_reset'));
    }

    /**
     * El usuario a recuperar viene únicamente de la sesión del servidor: nunca de un ID en la URL
     * ni en el cuerpo de la petición. El permiso vence a los pocos minutos de verificarse.
     */
    private function verifiedUser(Request $request): ?User
    {
        $userId = $request->session()->get('recovery.verified_user_id');
        $verifiedAt = $request->session()->get('recovery.verified_at');

        if (! $userId || ! $verifiedAt) {
            return null;
        }

        if (now()->diffInMinutes($verifiedAt, absolute: true) > AccountRecoveryService::RECOVERY_GRANT_TTL_MINUTES) {
            return null;
        }

        return User::find($userId);
    }
}
