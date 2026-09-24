<?php

namespace App\Models;

use App\Enums\PondType;
use Database\Factories\PondFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Gate;

class Pond extends Model
{
    /** @use HasFactory<PondFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'farm_id',
        'code',
        'name',
        'type',
        'surface_m2',
        'volume_m3',
        'depth_m',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PondType::class,
            'surface_m2' => 'decimal:2',
            'volume_m3' => 'decimal:2',
            'depth_m' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Farm, $this>
     */
    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
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
     * Un estanque es visible para quien puede ver su finca (mismas reglas de Farm::visibleTo).
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        if (Gate::forUser($user)->allows('manage-ponds')) {
            return;
        }

        $query->whereHas('farm', fn (Builder $query) => $query->visibleTo($user));
    }
}
