<?php

namespace App\Models;

use App\Enums\BatchStatus;
use Database\Factories\BatchFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

/**
 * Lote o ciclo de producción sembrado en un estanque.
 */
class Batch extends Model
{
    /** @use HasFactory<BatchFactory> */
    use HasFactory;

    /**
     * El estado y la fecha de cierre solo cambian con las acciones de cerrar y cancelar.
     *
     * @var list<string>
     */
    protected $fillable = [
        'pond_id',
        'species_id',
        'code',
        'name',
        'stocked_at',
        'initial_quantity',
        'initial_average_weight_g',
        'notes',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'activo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stocked_at' => 'date',
            'closed_at' => 'date',
            'initial_quantity' => 'integer',
            'initial_average_weight_g' => 'decimal:2',
            'status' => BatchStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Pond, $this>
     */
    public function pond(): BelongsTo
    {
        return $this->belongsTo(Pond::class);
    }

    /**
     * @return BelongsTo<Species, $this>
     */
    public function species(): BelongsTo
    {
        return $this->belongsTo(Species::class);
    }

    /**
     * @return HasMany<Feeding, $this>
     */
    public function feedings(): HasMany
    {
        return $this->hasMany(Feeding::class);
    }

    /**
     * @return HasMany<Mortality, $this>
     */
    public function mortalities(): HasMany
    {
        return $this->hasMany(Mortality::class);
    }

    /**
     * @return HasMany<Sampling, $this>
     */
    public function samplings(): HasMany
    {
        return $this->hasMany(Sampling::class);
    }

    /**
     * Biometría más reciente (por fecha y, a igual fecha, la última registrada).
     *
     * @return HasOne<Sampling, $this>
     */
    public function latestSampling(): HasOne
    {
        return $this->hasOne(Sampling::class)->ofMany(['sampled_on' => 'max', 'id' => 'max']);
    }

    /**
     * Fecha del primer registro productivo; la siembra no puede moverse después de ella.
     */
    public function firstRecordDate(): ?Carbon
    {
        $dates = array_filter([
            $this->feedings()->min('fed_on'),
            $this->mortalities()->min('recorded_on'),
            $this->samplings()->min('sampled_on'),
        ]);

        return $dates ? Carbon::parse(min($dates)) : null;
    }

    public function isActive(): bool
    {
        return $this->status === BatchStatus::Active;
    }

    /**
     * Días de cultivo desde la siembra hasta el cierre (o hasta hoy si sigue activo).
     */
    public function daysInCulture(): int
    {
        return (int) $this->stocked_at->diffInDays($this->closed_at ?? Carbon::today());
    }

    public function close(BatchStatus $status, Carbon $closedAt): void
    {
        $this->forceFill(['status' => $status, 'closed_at' => $closedAt])->save();
    }

    public function isVisibleTo(User $user): bool
    {
        return static::query()->visibleTo($user)->whereKey($this->getKey())->exists();
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function search(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        $query->where(fn (Builder $query) => $query
            ->where('code', 'like', '%'.$term.'%')
            ->orWhere('name', 'like', '%'.$term.'%'));
    }

    /**
     * Un lote es visible para quien puede ver su estanque (y por tanto su finca).
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        if (Gate::forUser($user)->allows('manage-batches')) {
            return;
        }

        $query->whereHas('pond', fn (Builder $query) => $query->visibleTo($user));
    }
}
