<?php

namespace App\Http\Controllers;

use App\Http\Requests\Sensors\StoreSensorRequest;
use App\Http\Requests\Sensors\UpdateSensorRequest;
use App\Models\Device;
use App\Models\Metric;
use App\Models\Sensor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SensorController extends Controller
{
    public function create(Device $device): View
    {
        Gate::authorize('create', Sensor::class);

        return view('devices.sensors.create', [
            'device' => $device->load('farm'),
            'sensor' => new Sensor(['device_id' => $device->id, 'is_active' => true]),
            'ponds' => $device->farm->ponds()->active()->orderBy('code')->pluck('code', 'id')->all(),
            'metrics' => Metric::active()->orderBy('name')->pluck('name', 'id')->all(),
        ]);
    }

    public function store(StoreSensorRequest $request, Device $device): RedirectResponse
    {
        $sensor = $device->sensors()->create($request->validated());

        return redirect()->route('devices.show', $device)
            ->with('status', "Se registró el sensor {$sensor->code}.");
    }

    public function edit(Device $device, Sensor $sensor): View
    {
        abort_unless($sensor->device_id === $device->id, 404);
        Gate::authorize('update', $sensor);

        return view('devices.sensors.edit', [
            'device' => $device->load('farm'),
            'sensor' => $sensor,
            'ponds' => $device->farm->ponds()->active()->orderBy('code')->pluck('code', 'id')->all(),
            'metrics' => Metric::active()->orderBy('name')->pluck('name', 'id')->all(),
        ]);
    }

    public function update(UpdateSensorRequest $request, Device $device, Sensor $sensor): RedirectResponse
    {
        abort_unless($sensor->device_id === $device->id, 404);

        $sensor->update($request->validated());

        return redirect()->route('devices.show', $device)
            ->with('status', "Se actualizaron los datos del sensor {$sensor->code}.");
    }
}
