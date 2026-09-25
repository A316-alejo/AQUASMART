<?php

namespace App\Policies;

use App\Enums\AlertStatus;
use App\Models\Alert;
use App\Models\Farm;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class AlertPolicy
{
    /**
     * Quien puede consultar fincas puede consultar las alertas de las fincas que ve.
     */
    public function viewAny(User $user): bool
    {
        return Gate::forUser($user)->allows('viewAny', Farm::class);
    }

    public function view(User $user, Alert $alert): bool
    {
        return Gate::forUser($user)->allows('manage-batches') || $alert->isVisibleTo($user);
    }

    /**
     * Reconocer y resolver alertas: los mismos roles que registran datos productivos
     * (admin, productor, técnico) sobre alertas de lotes a los que tienen acceso.
     * Consulta solo lee: nunca puede gestionar alertas.
     */
    public function acknowledge(User $user, Alert $alert): bool
    {
        return $this->canManage($user, $alert) && $alert->status === AlertStatus::Pending;
    }

    public function resolve(User $user, Alert $alert): bool
    {
        return $this->canManage($user, $alert) && $alert->status !== AlertStatus::Resolved;
    }

    private function canManage(User $user, Alert $alert): bool
    {
        return Gate::forUser($user)->allows('record-production') && $alert->isVisibleTo($user);
    }
}
