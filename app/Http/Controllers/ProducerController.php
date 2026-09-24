<?php

namespace App\Http\Controllers;

use App\Enums\DocumentType;
use App\Enums\PersonType;
use App\Http\Requests\Producers\StoreProducerRequest;
use App\Http\Requests\Producers\UpdateProducerRequest;
use App\Models\Producer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProducerController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Producer::class);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        $producers = Producer::query()
            ->visibleTo($request->user())
            ->search($filters['q'] ?? null)
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('is_active', $status === 'active'))
            ->withCount('users')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $totals = Gate::allows('manage-producers')
            ? Producer::toBase()
                ->selectRaw('COUNT(*) AS total')
                ->selectRaw('SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS active')
                ->selectRaw('SUM(CASE WHEN person_type = ? THEN 1 ELSE 0 END) AS juridical', [PersonType::Juridical->value])
                ->first()
            : null;

        return view('producers.index', [
            'producers' => $producers,
            'filters' => $filters,
            'totals' => $totals,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Producer::class);

        return view('producers.create', [
            'producer' => new Producer([
                'person_type' => PersonType::Natural,
                'document_type' => DocumentType::CitizenId,
                'is_active' => true,
            ]),
        ]);
    }

    public function store(StoreProducerRequest $request): RedirectResponse
    {
        $producer = Producer::create($request->validated());

        return redirect()->route('producers.show', $producer)
            ->with('status', "Se registró el productor {$producer->name}.");
    }

    public function show(Request $request, Producer $producer): View
    {
        Gate::authorize('view', $producer);

        if (Gate::allows('manage-users')) {
            $producer->load(['users' => fn ($query) => $query->orderBy('name')]);
        }

        return view('producers.show', [
            'producer' => $producer,
            'farms' => $producer->farms()->visibleTo($request->user())->orderBy('name')->get(),
        ]);
    }

    public function edit(Producer $producer): View
    {
        Gate::authorize('update', $producer);

        return view('producers.edit', ['producer' => $producer]);
    }

    public function update(UpdateProducerRequest $request, Producer $producer): RedirectResponse
    {
        $producer->update($request->validated());

        return redirect()->route('producers.show', $producer)
            ->with('status', "Se actualizaron los datos de {$producer->name}.");
    }
}
