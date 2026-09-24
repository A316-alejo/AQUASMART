<?php

namespace App\Http\Controllers;

use App\Enums\PondType;
use App\Http\Requests\Ponds\StorePondRequest;
use App\Http\Requests\Ponds\UpdatePondRequest;
use App\Models\Farm;
use App\Models\Pond;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PondController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Pond::class);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'farm' => ['nullable', 'integer'],
            'type' => ['nullable', Rule::enum(PondType::class)],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        $ponds = Pond::query()
            ->visibleTo($request->user())
            ->search($filters['q'] ?? null)
            ->when($filters['farm'] ?? null, fn (Builder $query, int $farmId) => $query->where('farm_id', $farmId))
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('type', $type))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('is_active', $status === 'active'))
            ->with(['farm:id,name,producer_id,is_active', 'farm.producer:id,name'])
            ->orderBy('code')
            ->paginate(15)
            ->withQueryString();

        $manages = Gate::allows('manage-ponds');

        return view('ponds.index', [
            'ponds' => $ponds,
            'filters' => $filters,
            'farms' => Farm::query()->visibleTo($request->user())->orderBy('name')->pluck('name', 'id')->all(),
            'totals' => $manages
                ? Pond::toBase()
                    ->selectRaw('COUNT(*) AS total')
                    ->selectRaw('SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS active')
                    ->selectRaw('COALESCE(SUM(volume_m3), 0) AS volume')
                    ->first()
                : null,
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Pond::class);

        return view('ponds.create', [
            'pond' => new Pond([
                'farm_id' => $request->integer('farm') ?: null,
                'type' => PondType::Geomembrane,
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

    public function store(StorePondRequest $request): RedirectResponse
    {
        $pond = Pond::create($request->validated());

        return redirect()->route('ponds.show', $pond)
            ->with('status', "Se registró el estanque {$pond->code}.");
    }

    public function show(Pond $pond): View
    {
        Gate::authorize('view', $pond);

        return view('ponds.show', ['pond' => $pond->load('farm.producer')]);
    }

    public function edit(Pond $pond): View
    {
        Gate::authorize('update', $pond);

        return view('ponds.edit', ['pond' => $pond->load('farm.producer')]);
    }

    public function update(UpdatePondRequest $request, Pond $pond): RedirectResponse
    {
        $pond->update($request->validated());

        return redirect()->route('ponds.show', $pond)
            ->with('status', "Se actualizaron los datos del estanque {$pond->code}.");
    }
}
