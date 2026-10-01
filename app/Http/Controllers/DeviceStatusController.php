<?php

namespace App\Http\Controllers;

use App\Models\Device;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DeviceStatusController extends Controller
{
    public function confirmDeactivation(Device $device): View
    {
        Gate::authorize('deactivate', $device);

        return view('devices.deactivate', ['device' => $device->load('farm')->loadCount('sensors')]);
    }

    public function deactivate(Device $device): RedirectResponse
    {
        Gate::authorize('deactivate', $device);

        $device->update(['is_active' => false]);

        return redirect()->route('devices.show', $device)
            ->with('status', "{$device->code} fue desactivado.");
    }

    public function activate(Device $device): RedirectResponse
    {
        Gate::authorize('activate', $device);

        $device->update(['is_active' => true]);

        return redirect()->route('devices.show', $device)
            ->with('status', "{$device->code} fue activado.");
    }
}
