<?php

namespace App\Models;

use App\Enums\AlertStatus;
use App\Enums\Role;
use App\Enums\WaterQualityStatus;
use Database\Factories\AlertFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Gate;

/**
 * Alerta generada a partir de una medición de calidad del agua evaluada como advertencia o crítica
 * (ver App\Services\AlertGenerator). Conserva su propio value/severity de forma independiente:
 * si después cambian los umbrales en alert_rules, las alertas ya emitidas no se alteran.
 */
class Alert extends Model
{
    /** @use HasFactory<AlertFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'batch_id',
        'metric_id',
        'water_quality_reading_id',
        'value',
        'severity',
        'status',
        'acknowledged_at',
        'acknowledged_by_id',
        'resolved_at',
        'resolved_by_id',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pendiente',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'decimal:3',
            'severity' => WaterQualityStatus::class,
            'status' => AlertStatus::class,
            'acknowledged_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Batch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    /**
     * @return BelongsTo<Metric, $this>
     */
    public function metric(): BelongsTo
    {
        return $this->belongsTo(Metric::class);
    }

    /**
     * @return BelongsTo<WaterQualityReading, $this>
     */
    public function reading(): BelongsTo
    {
        return $this->belongsTo(WaterQualityReading::class, 'water_quality_reading_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_id');
    }

    public function isPending(): bool
    {
        return $this->status === AlertStatus::Pending;
    }

    public function acknowledge(User $user): void
    {
        $this->forceFill([
            'status' => AlertStatus::Acknowledged,
            'acknowledged_at' => now(),
            'acknowledged_by_id' => $user->id,
        ])->save();
    }

    /**
     * Se puede resolver directamente desde pendiente, sin pasar por reconocida.
     */
    public function resolve(User $user): void
    {
        $this->forceFill([
            'status' => AlertStatus::Resolved,
            'resolved_at' => now(),
            'resolved_by_id' => $user->id,
        ])->save();
    }

    /**
     * Si la finca del lote ya está cargada (típico en un listado), evalúa la misma regla de
     * Farm::visibleTo() en memoria en lugar de repetir la consulta por cada fila (evita N+1
     * al mostrar los botones de reconocer/resolver en el Centro de alertas).
     */
    public function isVisibleTo(User $user): bool
    {
        if (Gate::forUser($user)->allows('manage-batches')) {
            return true;
        }

        $farm = $this->preloadedFarm();

        if (! $farm) {
            return static::query()->visibleTo($user)->whereKey($this->getKey())->exists();
        }

        if ($user->role === Role::Producer) {
            return $farm->producer_id === $user->producer_id;
        }

        return $farm->users->contains($user->getKey())
            && ($user->producer_id === null || $farm->producer_id === $user->producer_id);
    }

    /**
     * La finca del lote, solo si toda la cadena (batch.pond.farm.users) ya está cargada.
     */
    private function preloadedFarm(): ?Farm
    {
        if (! $this->relationLoaded('batch') || ! $this->batch->relationLoaded('pond')) {
            return null;
        }

        $pond = $this->batch->pond;

        if (! $pond->relationLoaded('farm') || ! $pond->farm->relationLoaded('users')) {
            return null;
        }

        return $pond->farm;
    }

    /**
     * Una alerta es visible para quien puede ver el lote que la originó (y por tanto su finca).
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        if (Gate::forUser($user)->allows('manage-batches')) {
            return;
        }

        $query->whereHas('batch', fn (Builder $query) => $query->visibleTo($user));
    }
}
