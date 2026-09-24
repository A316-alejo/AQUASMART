<?php

namespace App\Policies;

use App\Models\Batch;
use App\Models\Pond;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class BatchPolicy
{
    /**
     * Quien puede consultar estanques puede consultar los lotes de los estanques que ve.
     */
    public function viewAny(User $user): bool
    {
        return Gate::forUser($user)->allows('viewAny', Pond::class);
    }

    public function view(User $user, Batch $batch): bool
    {
        return $this->manages($user) || $batch->isVisibleTo($user);
    }

    public function create(User $user): bool
    {
        return $this->manages($user);
    }

    /**
     * Un lote cerrado o cancelado queda como registro histórico y no se edita.
     */
    public function update(User $user, Batch $batch): bool
    {
        return $this->manages($user) && $batch->isActive();
    }

    public function close(User $user, Batch $batch): bool
    {
        return $this->manages($user) && $batch->isActive();
    }

    public function cancel(User $user, Batch $batch): bool
    {
        return $this->manages($user) && $batch->isActive();
    }

    /**
     * Registrar alimentación, mortalidad y biometrías: roles operativos (admin, productor, técnico)
     * sobre lotes activos que puedan consultar. El rol consulta es de solo lectura.
     */
    public function record(User $user, Batch $batch): bool
    {
        return $batch->isActive()
            && Gate::forUser($user)->allows('record-production')
            && ($this->manages($user) || $batch->isVisibleTo($user));
    }

    private function manages(User $user): bool
    {
        return Gate::forUser($user)->allows('manage-batches');
    }
}
