<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Farm;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class FarmPolicy
{
    /**
     * Un productor sin productor asociado no tiene fincas que consultar; técnico y consulta ven las que tengan asignadas.
     */
    public function viewAny(User $user): bool
    {
        return match (true) {
            $this->manages($user) => true,
            $user->role === Role::Producer => $user->producer_id !== null,
            default => $user->hasRole(Role::Technician, Role::Viewer),
        };
    }

    public function view(User $user, Farm $farm): bool
    {
        return $this->manages($user) || $farm->isVisibleTo($user);
    }

    public function create(User $user): bool
    {
        return $this->manages($user);
    }

    public function update(User $user, Farm $farm): bool
    {
        return $this->manages($user);
    }

    public function deactivate(User $user, Farm $farm): bool
    {
        return $this->manages($user) && $farm->is_active;
    }

    public function activate(User $user, Farm $farm): bool
    {
        return $this->manages($user) && ! $farm->is_active;
    }

    public function manageAssignments(User $user, Farm $farm): bool
    {
        return $this->manages($user);
    }

    private function manages(User $user): bool
    {
        return Gate::forUser($user)->allows('manage-farms');
    }
}
