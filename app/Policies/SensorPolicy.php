<?php

namespace App\Policies;

use App\Models\Farm;
use App\Models\Sensor;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class SensorPolicy
{
    public function viewAny(User $user): bool
    {
        return Gate::forUser($user)->allows('viewAny', Farm::class);
    }

    public function view(User $user, Sensor $sensor): bool
    {
        return $this->manages($user) || $sensor->isVisibleTo($user);
    }

    public function create(User $user): bool
    {
        return $this->manages($user);
    }

    public function update(User $user, Sensor $sensor): bool
    {
        return $this->manages($user);
    }

    public function deactivate(User $user, Sensor $sensor): bool
    {
        return $this->manages($user) && $sensor->is_active;
    }

    public function activate(User $user, Sensor $sensor): bool
    {
        return $this->manages($user) && ! $sensor->is_active && $sensor->device->is_active;
    }

    private function manages(User $user): bool
    {
        return Gate::forUser($user)->allows('manage-devices');
    }
}
