<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Http\Requests\Farms\StoreFarmRequest;
use App\Http\Requests\Farms\UpdateFarmRequest;
use App\Models\Farm;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FarmController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Farm::class);

        $manages = Gate::allows('manage-farms');

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'producer' => ['nullable', 'integer'],
            'department' => ['nullable', 'string', Rule::in(config('aquasmart.departments'))],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        $farms = Farm::query()
            ->visibleTo($request->user())
            ->search($filters['q'] ?? null)
            ->when($filters['producer'] ?? null, fn (Builder $query, int $producerId) => $query->where('producer_id', $producerId))
            ->when($filters['department'] ?? null, fn (Builder $query, string $department) => $query->where('department', $department))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('is_active', $status === 'active'))
            ->with('producer:id,name,is_active')
            ->withCount('users')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('farms.index', [
            'farms' => $farms,
            'filters' => $filters,
            'manages' => $manages,
            'producers' => $manages ? Producer::orderBy('name')->pluck('name', 'id')->all() : [],
            'totals' => $manages
                ? Farm::toBase()
                    ->selectRaw('COUNT(*) AS total')
                    ->selectRaw('SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS active')
                    ->selectRaw('COALESCE(SUM(area_ha), 0) AS area')
                    ->first()
                : null,
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Farm::class);

        return view('farms.create', [
            'farm' => new Farm([
                'producer_id' => $request->integer('producer') ?: null,
                'timezone' => config('app.timezone'),
                'is_active' => true,
            ]),
            'producers' => Producer::active()->orderBy('name')->pluck('name', 'id')->all(),
        ]);
    }

    public function store(StoreFarmRequest $request): RedirectResponse
    {
        $farm = Farm::create($request->validated());

        return redirect()->route('farms.show', $farm)
            ->with('status', "Se registró la finca {$farm->name}.");
    }

    public function show(Farm $farm): View
    {
        Gate::authorize('view', $farm);

        $farm->load([
            'producer',
            'users' => fn ($query) => $query->orderBy('name'),
        ]);

        return view('farms.show', [
            'farm' => $farm,
            'assignableUsers' => Gate::allows('manageAssignments', $farm) ? $this->assignableUsers($farm) : collect(),
        ]);
    }

    public function edit(Farm $farm): View
    {
        Gate::authorize('update', $farm);

        return view('farms.edit', ['farm' => $farm->load('producer')]);
    }

    public function update(UpdateFarmRequest $request, Farm $farm): RedirectResponse
    {
        $farm->update($request->validated());

        return redirect()->route('farms.show', $farm)
            ->with('status', "Se actualizaron los datos de {$farm->name}.");
    }

    /**
     * Técnicos y usuarios de consulta activos, sin productor o del mismo productor de la finca, aún no asignados.
     *
     * @return Collection<int, User>
     */
    private function assignableUsers(Farm $farm)
    {
        return User::query()
            ->whereIn('role', [Role::Technician->value, Role::Viewer->value])
            ->where('is_active', true)
            ->where(fn (Builder $query) => $query->whereNull('producer_id')->orWhere('producer_id', $farm->producer_id))
            ->whereDoesntHave('farms', fn (Builder $query) => $query->whereKey($farm->getKey()))
            ->orderBy('name')
            ->get(['id', 'name', 'role']);
    }
}
