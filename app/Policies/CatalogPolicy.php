<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Autorización compartida por los catálogos (especies, alimentos, causas de mortalidad y parámetros).
 * Son datos de referencia comunes a todos los productores: cualquier usuario activo los consulta,
 * solo el administrador los modifica.
 */
class CatalogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function create(User $user): bool
    {
        return $this->manages($user);
    }

    public function update(User $user, Model $item): bool
    {
        return $this->manages($user);
    }

    public function deactivate(User $user, Model $item): bool
    {
        return $this->manages($user) && $item->is_active;
    }

    public function activate(User $user, Model $item): bool
    {
        return $this->manages($user) && ! $item->is_active;
    }

    private function manages(User $user): bool
    {
        return Gate::forUser($user)->allows('manage-catalogs');
    }
}
