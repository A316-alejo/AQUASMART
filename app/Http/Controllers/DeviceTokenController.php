<?php

namespace App\Http\Controllers;

use App\Models\Device;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class DeviceTokenController extends Controller
{
    /**
     * El token nuevo solo se muestra una vez, vía flash de sesión, en la página de detalle.
     */
    public function regenerate(Device $device): RedirectResponse
    {
        Gate::authorize('regenerateToken', $device);

        $token = $device->generateToken();

        return redirect()->route('devices.show', $device)
            ->with('status', "Se generó un nuevo token para {$device->code}. El token anterior dejó de funcionar.")
            ->with('device_token', $token);
    }
}
