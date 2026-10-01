<?php

namespace App\Models;

use Database\Factories\SensorFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Gate;

/**
 * Sensor de un dispositivo que mide un parámetro de `metrics` en un estanque concreto.
 * No duplica el catálogo: unidad y rango físico se consultan siempre desde Metric.
 */
class Sensor extends Model
{
    /** @use HasFactory<SensorFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'device_id',
        'pond_id',
        'metric_id',
        'name',
        'code',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Device, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /**
     * @return BelongsTo<Pond, $this>
     */
    public function pond(): BelongsTo
    {
        return $this->belongsTo(Pond::class);
    }

    /**
     * @return BelongsTo<Metric, $this>
     */
    public function metric(): BelongsTo
    {
        return $this->belongsTo(Metric::class);
    }

    public function isVisibleTo(User $user): bool
    {
        return static::query()->visibleTo($user)->whereKey($this->getKey())->exists();
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Un sensor es visible para quien puede ver el dispositivo al que pertenece.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        if (Gate::forUser($user)->allows('manage-devices')) {
            return;
        }

        $query->whereHas('device', fn (Builder $query) => $query->visibleTo($user));
    }
}
