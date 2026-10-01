<?php

namespace App\Http\Controllers;

use App\Enums\DeviceType;
use App\Http\Requests\Devices\StoreDeviceRequest;
use App\Http\Requests\Devices\UpdateDeviceRequest;
use App\Models\Device;
use App\Models\Farm;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DeviceController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Device::class);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'farm' => ['nullable', 'integer'],
            'type' => ['nullable', Rule::enum(DeviceType::class)],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        $devices = Device::query()
            ->visibleTo($request->user())
            ->search($filters['q'] ?? null)
            ->when($filters['farm'] ?? null, fn (Builder $query, int $farmId) => $query->where('farm_id', $farmId))
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('type', $type))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('is_active', $status === 'active'))
            ->with('farm:id,name,producer_id')
            ->withCount('sensors')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('devices.index', [
            'devices' => $devices,
            'filters' => $filters,
            'manages' => Gate::allows('manage-devices'),
            'farms' => Farm::query()->visibleTo($request->user())->orderBy('name')->pluck('name', 'id')->all(),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Device::class);

        return view('devices.create', [
            'device' => new Device([
                'farm_id' => $request->integer('farm') ?: null,
                'type' => DeviceType::Esp32,
                'is_active' => true,
            ]),
            'farms' => Farm::active()
                ->with('producer:id,name')
                ->orderBy('name')
                ->get(['id', 'name', 'producer_id'])
                ->mapWithKeys(fn (Farm $farm) => [$farm->id => $farm->name.' — '.$farm->producer->name])
                ->all(),
        ]);
    }

    public function store(StoreDeviceRequest $request): RedirectResponse
    {
        $device = Device::create($request->validated());
        $token = $device->generateToken();

        return redirect()->route('devices.show', $device)
            ->with('status', "Se registró el dispositivo {$device->code}.")
            ->with('device_token', $token);
    }

    public function show(Device $device): View
    {
        Gate::authorize('view', $device);

        $device->load(['farm.producer', 'sensors' => fn ($query) => $query->with(['pond:id,code', 'metric:id,name,unit'])->orderBy('code')]);

        return view('devices.show', ['device' => $device]);
    }

    public function edit(Device $device): View
    {
        Gate::authorize('update', $device);

        return view('devices.edit', ['device' => $device->load('farm.producer')]);
    }

    public function update(UpdateDeviceRequest $request, Device $device): RedirectResponse
    {
        $device->update($request->validated());

        return redirect()->route('devices.show', $device)
            ->with('status', "Se actualizaron los datos del dispositivo {$device->code}.");
    }
}
