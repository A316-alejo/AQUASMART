<?php

namespace App\Http\Controllers;

use App\Models\Pond;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PondStatusController extends Controller
{
    public function confirmDeactivation(Pond $pond): View
    {
        Gate::authorize('deactivate', $pond);

        return view('ponds.deactivate', ['pond' => $pond->load('farm')]);
    }

    public function deactivate(Pond $pond): RedirectResponse
    {
        Gate::authorize('deactivate', $pond);

        $pond->update(['is_active' => false]);

        return redirect()->route('ponds.show', $pond)
            ->with('status', "El estanque {$pond->code} fue desactivado.");
    }

    public function activate(Pond $pond): RedirectResponse
    {
        Gate::authorize('activate', $pond);

        $pond->update(['is_active' => true]);

        return redirect()->route('ponds.show', $pond)
            ->with('status', "El estanque {$pond->code} fue activado.");
    }
}
