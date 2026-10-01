<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\Sensor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SensorStatusController extends Controller
{
    public function confirmDeactivation(Device $device, Sensor $sensor): View
    {
        abort_unless($sensor->device_id === $device->id, 404);
        Gate::authorize('deactivate', $sensor);

        return view('devices.sensors.deactivate', ['device' => $device, 'sensor' => $sensor->load('pond')]);
    }

    public function deactivate(Device $device, Sensor $sensor): RedirectResponse
    {
        abort_unless($sensor->device_id === $device->id, 404);
        Gate::authorize('deactivate', $sensor);

        $sensor->update(['is_active' => false]);

        return redirect()->route('devices.show', $device)
            ->with('status', "El sensor {$sensor->code} fue desactivado.");
    }

    public function activate(Device $device, Sensor $sensor): RedirectResponse
    {
        abort_unless($sensor->device_id === $device->id, 404);
        Gate::authorize('activate', $sensor);

        $sensor->update(['is_active' => true]);

        return redirect()->route('devices.show', $device)
            ->with('status', "El sensor {$sensor->code} fue activado.");
    }
}
