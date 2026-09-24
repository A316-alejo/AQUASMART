<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\FarmFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Gate;

class Farm extends Model
{
    /** @use HasFactory<FarmFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'producer_id',
        'name',
        'location',
        'municipality',
        'department',
        'latitude',
        'longitude',
        'area_ha',
        'timezone',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'area_ha' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Producer, $this>
     */
    public function producer(): BelongsTo
    {
        return $this->belongsTo(Producer::class);
    }

    /**
     * Personal asignado a la finca (técnicos y usuarios de consulta).
     *
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    /**
     * Regla de lectura compartida por el listado (scope) y la Policy.
     */
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
            ->where('name', 'like', '%'.$term.'%')
            ->orWhere('location', 'like', '%'.$term.'%')
            ->orWhere('municipality', 'like', '%'.$term.'%'));
    }

    /**
     * Fincas que el usuario puede consultar:
     * - administrador: todas;
     * - productor: las de su productor;
     * - técnico y consulta: las asignadas en farm_user y, si su cuenta pertenece a un productor, solo las de ese productor.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        if (Gate::forUser($user)->allows('manage-farms')) {
            return;
        }

        if ($user->role === Role::Producer) {
            $query->where('producer_id', $user->producer_id ?? 0);

            return;
        }

        $query->whereHas('users', fn (Builder $query) => $query->whereKey($user->getKey()))
            ->when($user->producer_id, fn (Builder $query, int $producerId) => $query->where('producer_id', $producerId));
    }
}
