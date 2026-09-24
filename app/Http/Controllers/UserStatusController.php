<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class UserStatusController extends Controller
{
    public function confirmDeactivation(User $user): View
    {
        Gate::authorize('deactivate', $user);

        return view('users.deactivate', ['user' => $user]);
    }

    public function deactivate(User $user): RedirectResponse
    {
        Gate::authorize('deactivate', $user);

        $user->update(['is_active' => false]);
        $user->endAllSessions();

        return redirect()->route('users.index')
            ->with('status', "{$user->name} fue desactivado y ya no puede iniciar sesión.");
    }

    public function activate(User $user): RedirectResponse
    {
        Gate::authorize('activate', $user);

        $user->update(['is_active' => true]);

        return redirect()->route('users.index')
            ->with('status', "{$user->name} fue activado y ya puede iniciar sesión.");
    }
}
