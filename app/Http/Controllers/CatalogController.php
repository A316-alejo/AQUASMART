<?php

namespace App\Http\Controllers;

use App\Enums\Catalog;
use App\Http\Requests\Catalogs\StoreCatalogItemRequest;
use App\Http\Requests\Catalogs\UpdateCatalogItemRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CatalogController extends Controller
{
    public function index(Request $request, Catalog $catalog): View
    {
        Gate::authorize('viewAny', $catalog->model());

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        $items = $catalog->query()
            ->search($filters['q'] ?? null)
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('is_active', $status === 'active'))
            ->ordered()
            ->paginate(15)
            ->withQueryString();

        return view('catalogs.index', [
            'catalog' => $catalog,
            'items' => $items,
            'filters' => $filters,
            'counts' => collect(Catalog::cases())->mapWithKeys(fn (Catalog $case) => [$case->value => $case->query()->count()]),
        ]);
    }

    public function create(Catalog $catalog): View
    {
        Gate::authorize('create', $catalog->model());

        $item = $catalog->query()->newModelInstance(['is_active' => true]);

        if ($catalog === Catalog::Metrics) {
            $item->decimals = 2;
        }

        return view('catalogs.create', ['catalog' => $catalog, 'item' => $item]);
    }

    public function store(StoreCatalogItemRequest $request, Catalog $catalog): RedirectResponse
    {
        $item = $catalog->query()->create($request->validated());

        return redirect()->route('catalogs.index', $catalog)
            ->with('status', 'Se registró '.$catalog->gender('el', 'la').' '.$catalog->singular().' '.$item->catalogLabel().'.');
    }

    public function edit(Catalog $catalog, Model $item): View
    {
        Gate::authorize('update', $item);

        return view('catalogs.edit', ['catalog' => $catalog, 'item' => $item]);
    }

    public function update(UpdateCatalogItemRequest $request, Catalog $catalog, Model $item): RedirectResponse
    {
        $item->update($request->validated());

        return redirect()->route('catalogs.index', $catalog)
            ->with('status', 'Se actualizó '.$catalog->gender('el', 'la').' '.$catalog->singular().' '.$item->catalogLabel().'.');
    }
}
