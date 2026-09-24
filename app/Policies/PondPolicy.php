<?php

namespace App\Policies;

use App\Models\Farm;
use App\Models\Pond;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class PondPolicy
{
    /**
     * Quien puede consultar fincas puede consultar los estanques de las fincas que ve.
     */
    public function viewAny(User $user): bool
    {
        return Gate::forUser($user)->allows('viewAny', Farm::class);
    }

    public function view(User $user, Pond $pond): bool
    {
        return $this->manages($user) || $pond->isVisibleTo($user);
    }

    public function create(User $user): bool
    {
        return $this->manages($user);
    }

    public function update(User $user, Pond $pond): bool
    {
        return $this->manages($user);
    }

    public function deactivate(User $user, Pond $pond): bool
    {
        return $this->manages($user) && $pond->is_active;
    }

    /**
     * Un estanque no se reactiva mientras su finca esté inactiva.
     */
    public function activate(User $user, Pond $pond): bool
    {
        return $this->manages($user) && ! $pond->is_active && $pond->farm->is_active;
    }

    private function manages(User $user): bool
    {
        return Gate::forUser($user)->allows('manage-ponds');
    }
}
