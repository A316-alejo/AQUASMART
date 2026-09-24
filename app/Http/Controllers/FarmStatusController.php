<?php

namespace App\Http\Controllers;

use App\Models\Farm;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class FarmStatusController extends Controller
{
    public function confirmDeactivation(Farm $farm): View
    {
        Gate::authorize('deactivate', $farm);

        return view('farms.deactivate', ['farm' => $farm->load('producer')->loadCount('users')]);
    }

    public function deactivate(Farm $farm): RedirectResponse
    {
        Gate::authorize('deactivate', $farm);

        $farm->update(['is_active' => false]);

        return redirect()->route('farms.show', $farm)
            ->with('status', "{$farm->name} fue desactivada.");
    }

    public function activate(Farm $farm): RedirectResponse
    {
        Gate::authorize('activate', $farm);

        $farm->update(['is_active' => true]);

        return redirect()->route('farms.show', $farm)
            ->with('status', "{$farm->name} fue activada.");
    }
}
