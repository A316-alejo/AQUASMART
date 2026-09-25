<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class AlertActionController extends Controller
{
    public function acknowledge(Alert $alert): RedirectResponse
    {
        Gate::authorize('acknowledge', $alert);

        $alert->acknowledge(request()->user());

        return redirect()->back(fallback: route('alerts.index'))->with('status', 'Alerta reconocida.');
    }

    public function resolve(Alert $alert): RedirectResponse
    {
        Gate::authorize('resolve', $alert);

        $alert->resolve(request()->user());

        return redirect()->back(fallback: route('alerts.index'))->with('status', 'Alerta resuelta.');
    }
}
