<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PondType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ponds\StorePondRequest;
use App\Http\Requests\Ponds\UpdatePondRequest;
use App\Http\Resources\PondResource;
use App\Models\Farm;
use App\Models\Pond;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * API REST de estanques (/api/v1/ponds), bajo sesión web (auth + active, ver routes/api.php).
 *
 * No reimplementa ninguna regla: reutiliza los mismos scopes del modelo, las mismas Policies/Gates
 * y los mismos Form Requests que ya usa el CRUD web (PondController). Lo único que cambia frente
 * a la versión web es el tipo de respuesta (JSON en vez de vistas/redirects) y que no hay pantallas
 * de confirmación para activar/desactivar. Sin destroy: los estanques nunca se eliminan físicamente.
 */
class PondController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Pond::class);

        // Mismos filtros y misma validación que PondController@index (web); se repiten aquí porque
        // no hay un Form Request compartido para el listado y no se debe tocar el controlador web.
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
            ->with('farm:id,name,producer_id,is_active')
            ->orderBy('code')
            ->paginate(15)
            ->withQueryString();

        return PondResource::collection($ponds);
    }

    /**
     * Datos para el formulario: fincas activas visibles para el usuario y tipos de estanque.
     */
    public function options(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Pond::class);

        return response()->json(['data' => [
            'farms' => Farm::active()->visibleTo($request->user())->orderBy('name')->get(['id', 'name']),
            'types' => PondType::options(),
        ]]);
    }

    public function show(Pond $pond): PondResource
    {
        Gate::authorize('view', $pond);

        return new PondResource($pond->load('farm.producer'));
    }

    /**
     * La autorización ('create', Pond::class) y la validación ya las aplicó StorePondRequest.
     */
    public function store(StorePondRequest $request): JsonResponse
    {
        $pond = Pond::create($request->validated());

        return (new PondResource($pond->load('farm.producer')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * La autorización ('update', $pond) y la validación ya las aplicó UpdatePondRequest.
     */
    public function update(UpdatePondRequest $request, Pond $pond): PondResource
    {
        $pond->update($request->validated());

        return new PondResource($pond->load('farm.producer'));
    }

    public function activate(Pond $pond): PondResource
    {
        Gate::authorize('activate', $pond);

        $pond->update(['is_active' => true]);

        return new PondResource($pond->load('farm.producer'));
    }

    public function deactivate(Pond $pond): PondResource
    {
        Gate::authorize('deactivate', $pond);

        $pond->update(['is_active' => false]);

        return new PondResource($pond->load('farm.producer'));
    }
}
