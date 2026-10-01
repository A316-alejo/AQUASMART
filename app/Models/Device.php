<?php

namespace App\Models;

use App\Enums\DeviceType;
use Database\Factories\DeviceFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Dispositivo físico (ESP32, gateway…) que envía mediciones a la API de ingestión.
 * El token de autenticación nunca se guarda en texto plano: solo su hash (token_hash).
 */
class Device extends Model
{
    /** @use HasFactory<DeviceFactory> */
    use HasFactory;

    /**
     * token_hash y last_connected_at no son de llenado masivo: solo cambian mediante
     * generateToken() y touchConnection(), nunca desde un formulario.
     *
     * @var list<string>
     */
    protected $fillable = [
        'farm_id',
        'name',
        'code',
        'type',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => DeviceType::class,
            'is_active' => 'boolean',
            'last_connected_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Farm, $this>
     */
    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    /**
     * @return HasMany<Sensor, $this>
     */
    public function sensors(): HasMany
    {
        return $this->hasMany(Sensor::class);
    }

    /**
     * Genera un token nuevo, guarda solo su hash y devuelve el valor en texto plano.
     * Es la única vez que el token completo está disponible: ni se guarda ni se vuelve a mostrar.
     */
    public function generateToken(): string
    {
        $token = Str::random(48);

        $this->forceFill(['token_hash' => hash('sha256', $token)])->save();

        return $token;
    }

    public function touchConnection(): void
    {
        $this->forceFill(['last_connected_at' => now()])->save();
    }

    public static function findByToken(string $token): ?self
    {
        return static::query()
            ->where('token_hash', hash('sha256', $token))
            ->where('is_active', true)
            ->first();
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
     * Un dispositivo es visible para quien puede ver su finca (mismas reglas de Farm::visibleTo).
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        if (Gate::forUser($user)->allows('manage-devices')) {
            return;
        }

        $query->whereHas('farm', fn (Builder $query) => $query->visibleTo($user));
    }
}
