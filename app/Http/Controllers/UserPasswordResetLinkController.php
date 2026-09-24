<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Password;

class UserPasswordResetLinkController extends Controller
{
    public function store(User $user): RedirectResponse
    {
        Gate::authorize('resetPassword', $user);

        $status = Password::sendResetLink(['email' => $user->email]);

        if ($status !== Password::RESET_LINK_SENT) {
            return back()->with('error', __($status));
        }

        return back()->with('status', "Se envió a {$user->email} un enlace para restablecer la contraseña.");
    }
}
