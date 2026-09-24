<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', User::class);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::enum(Role::class)],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        $users = User::query()
            ->when($filters['q'] ?? null, fn ($query, string $term) => $query->where(fn ($query) => $query
                ->where('name', 'like', '%'.$term.'%')
                ->orWhere('email', 'like', '%'.$term.'%')))
            ->when($filters['role'] ?? null, fn ($query, string $role) => $query->where('role', $role))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('is_active', $status === 'active'))
            ->with('producer:id,name,is_active')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $totals = User::toBase()
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS active')
            ->selectRaw('SUM(CASE WHEN role = ? AND is_active = 1 THEN 1 ELSE 0 END) AS admins', [Role::Admin->value])
            ->first();

        return view('users.index', [
            'users' => $users,
            'filters' => $filters,
            'totals' => $totals,
            'roles' => Role::cases(),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', User::class);

        return view('users.create', [
            'user' => new User(['role' => Role::Viewer, 'is_active' => true]),
            'roles' => Role::assignableBy($request->user()->role),
            'producers' => $this->producerOptions(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = User::create($request->validated());

        return redirect()->route('users.index')
            ->with('status', "Se creó el usuario {$user->name}.");
    }

    public function edit(Request $request, User $user): View
    {
        Gate::authorize('update', $user);

        return view('users.edit', [
            'user' => $user,
            'roles' => Role::assignableBy($request->user()->role),
            'producers' => $this->producerOptions(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->safe()->except(['password', 'current_password']);
        $newPassword = $request->validated('password');

        if ($newPassword !== null) {
            $data['password'] = $newPassword;
        }

        $user->update($data);

        if ($newPassword !== null) {
            // Si alguien más cambió la contraseña, se cierran todas las sesiones del usuario; si fue él mismo, solo las otras.
            $user->endAllSessions($request->user()->is($user) ? $request->session()->getId() : null);
        }

        return redirect()->route('users.index')
            ->with('status', "Se actualizaron los datos de {$user->name}.");
    }

    /**
     * @return array<int, string>
     */
    private function producerOptions(): array
    {
        return Producer::query()
            ->orderBy('name')
            ->get(['id', 'name', 'is_active'])
            ->mapWithKeys(fn (Producer $producer) => [
                $producer->id => $producer->name.($producer->is_active ? '' : ' (inactivo)'),
            ])
            ->all();
    }
}
