<?php

namespace App\Policies;

use App\Models\Producer;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class ProducerPolicy
{
    /**
     * El administrador ve todos los productores; los demás, solo si su cuenta pertenece a uno.
     */
    public function viewAny(User $user): bool
    {
        return $this->manages($user) || $user->producer_id !== null;
    }

    public function view(User $user, Producer $producer): bool
    {
        return $this->manages($user) || $user->producer_id === $producer->id;
    }

    public function create(User $user): bool
    {
        return $this->manages($user);
    }

    public function update(User $user, Producer $producer): bool
    {
        return $this->manages($user);
    }

    public function deactivate(User $user, Producer $producer): bool
    {
        return $this->manages($user) && $producer->is_active;
    }

    public function activate(User $user, Producer $producer): bool
    {
        return $this->manages($user) && ! $producer->is_active;
    }

    private function manages(User $user): bool
    {
        return Gate::forUser($user)->allows('manage-producers');
    }
}
