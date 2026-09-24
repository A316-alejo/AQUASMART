<?php

namespace App\Http\Controllers;

use App\Models\Producer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ProducerStatusController extends Controller
{
    public function confirmDeactivation(Producer $producer): View
    {
        Gate::authorize('deactivate', $producer);

        return view('producers.deactivate', ['producer' => $producer->loadCount('users')]);
    }

    public function deactivate(Producer $producer): RedirectResponse
    {
        Gate::authorize('deactivate', $producer);

        $producer->update(['is_active' => false]);

        // Las cuentas del productor pierden el acceso de inmediato, no en su próxima petición.
        $producer->users()->each(fn ($user) => $user->endAllSessions());

        return redirect()->route('producers.show', $producer)
            ->with('status', "{$producer->name} fue desactivado. Sus usuarios ya no pueden iniciar sesión.");
    }

    public function activate(Producer $producer): RedirectResponse
    {
        Gate::authorize('activate', $producer);

        $producer->update(['is_active' => true]);

        return redirect()->route('producers.show', $producer)
            ->with('status', "{$producer->name} fue activado.");
    }
}
