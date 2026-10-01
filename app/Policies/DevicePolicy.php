<?php

namespace App\Policies;

use App\Models\Device;
use App\Models\Farm;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class DevicePolicy
{
    /**
     * Quien puede consultar fincas puede consultar los dispositivos de las fincas que ve.
     */
    public function viewAny(User $user): bool
    {
        return Gate::forUser($user)->allows('viewAny', Farm::class);
    }

    public function view(User $user, Device $device): bool
    {
        return $this->manages($user) || $device->isVisibleTo($user);
    }

    public function create(User $user): bool
    {
        return $this->manages($user);
    }

    public function update(User $user, Device $device): bool
    {
        return $this->manages($user);
    }

    public function deactivate(User $user, Device $device): bool
    {
        return $this->manages($user) && $device->is_active;
    }

    public function activate(User $user, Device $device): bool
    {
        return $this->manages($user) && ! $device->is_active;
    }

    public function regenerateToken(User $user, Device $device): bool
    {
        return $this->manages($user);
    }

    private function manages(User $user): bool
    {
        return Gate::forUser($user)->allows('manage-devices');
    }
}
