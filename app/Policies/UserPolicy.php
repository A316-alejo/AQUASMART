<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Support\Facades\Gate;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return Gate::forUser($actor)->allows('manage-users');
    }

    public function create(User $actor): bool
    {
        return Gate::forUser($actor)->allows('manage-users');
    }

    public function update(User $actor, User $user): bool
    {
        return Gate::forUser($actor)->allows('manage-users');
    }

    /**
     * Nadie cambia su propio rol: evita autopromociones y que el último administrador se degrade.
     */
    public function changeRole(User $actor, User $user): bool
    {
        return Gate::forUser($actor)->allows('manage-users') && $actor->isNot($user);
    }

    /**
     * Un administrador no puede desactivarse a sí mismo, así siempre queda al menos uno activo.
     */
    public function deactivate(User $actor, User $user): bool
    {
        return Gate::forUser($actor)->allows('manage-users')
            && $actor->isNot($user)
            && $user->is_active;
    }

    public function activate(User $actor, User $user): bool
    {
        return Gate::forUser($actor)->allows('manage-users') && ! $user->is_active;
    }

    /**
     * Restablecer la contraseña de otra persona. La propia se cambia confirmando la contraseña actual.
     */
    public function resetPassword(User $actor, User $user): bool
    {
        return Gate::forUser($actor)->allows('manage-users') && $actor->isNot($user);
    }
}
