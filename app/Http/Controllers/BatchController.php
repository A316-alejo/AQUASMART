<?php

namespace App\Http\Controllers;

use App\Enums\BatchStatus;
use App\Http\Requests\Batches\StoreBatchRequest;
use App\Http\Requests\Batches\UpdateBatchRequest;
use App\Models\Batch;
use App\Models\Farm;
use App\Models\Pond;
use App\Models\Species;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BatchController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Batch::class);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'farm' => ['nullable', 'integer'],
            'species' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::enum(BatchStatus::class)],
        ]);

        $batches = Batch::query()
            ->visibleTo($request->user())
            ->search($filters['q'] ?? null)
            ->when($filters['farm'] ?? null, fn (Builder $query, int $farmId) => $query->whereHas('pond', fn (Builder $query) => $query->where('farm_id', $farmId)))
            ->when($filters['species'] ?? null, fn (Builder $query, int $speciesId) => $query->where('species_id', $speciesId))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->with(['pond:id,farm_id,code,name', 'pond.farm:id,name', 'species:id,common_name'])
            ->orderByDesc('stocked_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('batches.index', [
            'batches' => $batches,
            'filters' => $filters,
            'farms' => Farm::query()->visibleTo($request->user())->orderBy('name')->pluck('name', 'id')->all(),
            'species' => Species::query()->ordered()->pluck('common_name', 'id')->all(),
            'totals' => Gate::allows('manage-batches')
                ? Batch::toBase()
                    ->selectRaw("SUM(CASE WHEN status = 'activo' THEN 1 ELSE 0 END) AS active")
                    ->selectRaw("COALESCE(SUM(CASE WHEN status = 'activo' THEN initial_quantity ELSE 0 END), 0) AS stocked")
                    ->selectRaw("SUM(CASE WHEN status = 'cerrado' THEN 1 ELSE 0 END) AS closed")
                    ->selectRaw("SUM(CASE WHEN status = 'cancelado' THEN 1 ELSE 0 END) AS cancelled")
                    ->first()
                : null,
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Batch::class);

        return view('batches.create', [
            'batch' => new Batch([
                'pond_id' => $request->integer('pond') ?: null,
                'stocked_at' => today(),
            ]),
            'ponds' => $this->pondOptions(),
            'species' => Species::active()->ordered()->pluck('common_name', 'id')->all(),
        ]);
    }

    public function store(StoreBatchRequest $request): RedirectResponse
    {
        $batch = Batch::create($request->validated());

        return redirect()->route('batches.show', $batch)
            ->with('status', "Se registró el lote {$batch->code}.");
    }

    public function show(Batch $batch): View
    {
        Gate::authorize('view', $batch);

        return view('batches.show', ['batch' => $batch->load(['pond.farm.producer', 'species'])]);
    }

    public function edit(Batch $batch): View
    {
        Gate::authorize('update', $batch);

        return view('batches.edit', [
            'batch' => $batch->load(['pond.farm', 'species']),
            // Especies activas más la actual, por si fue desactivada después de la siembra.
            'species' => Species::query()
                ->where(fn (Builder $query) => $query->where('is_active', true)->orWhere('id', $batch->species_id))
                ->ordered()
                ->pluck('common_name', 'id')
                ->all(),
        ]);
    }

    public function update(UpdateBatchRequest $request, Batch $batch): RedirectResponse
    {
        $batch->update($request->validated());

        return redirect()->route('batches.show', $batch)
            ->with('status', "Se actualizaron los datos del lote {$batch->code}.");
    }

    /**
     * Estanques activos de fincas activas, etiquetados con su finca.
     *
     * @return array<int, string>
     */
    private function pondOptions(): array
    {
        return Pond::active()
            ->whereHas('farm', fn (Builder $query) => $query->active())
            ->with('farm:id,name')
            ->get(['id', 'farm_id', 'code', 'name'])
            ->sortBy(fn (Pond $pond) => $pond->farm->name.' '.$pond->code)
            ->mapWithKeys(fn (Pond $pond) => [$pond->id => "{$pond->farm->name} — {$pond->code} · {$pond->name}"])
            ->all();
    }
}
