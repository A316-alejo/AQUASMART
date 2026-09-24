<?php

namespace App\Http\Controllers;

use App\Enums\Catalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CatalogStatusController extends Controller
{
    public function confirmDeactivation(Catalog $catalog, Model $item): View
    {
        Gate::authorize('deactivate', $item);

        return view('catalogs.deactivate', ['catalog' => $catalog, 'item' => $item]);
    }

    public function deactivate(Catalog $catalog, Model $item): RedirectResponse
    {
        Gate::authorize('deactivate', $item);

        $item->update(['is_active' => false]);

        return redirect()->route('catalogs.index', $catalog)
            ->with('status', ucfirst($catalog->gender('el', 'la')).' '.$catalog->singular().' '.$item->catalogLabel().' fue '.$catalog->gender('desactivado', 'desactivada').'.');
    }

    public function activate(Catalog $catalog, Model $item): RedirectResponse
    {
        Gate::authorize('activate', $item);

        $item->update(['is_active' => true]);

        return redirect()->route('catalogs.index', $catalog)
            ->with('status', ucfirst($catalog->gender('el', 'la')).' '.$catalog->singular().' '.$item->catalogLabel().' fue '.$catalog->gender('activado', 'activada').'.');
    }
}
