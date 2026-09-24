<?php

namespace App\Models;

use App\Enums\DocumentType;
use App\Enums\PersonType;
use Database\Factories\ProducerFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Gate;

class Producer extends Model
{
    /** @use HasFactory<ProducerFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'person_type',
        'name',
        'document_type',
        'document_number',
        'phone',
        'email',
        'municipality',
        'department',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'person_type' => PersonType::class,
            'document_type' => DocumentType::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function documentLabel(): string
    {
        return $this->document_type->value.' '.$this->document_number;
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
     * Busca por nombre, número de documento o teléfono (ignorando puntos, espacios y guiones en los números).
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function search(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        $digits = preg_replace('/\D+/', '', $term);

        $query->where(function (Builder $query) use ($term, $digits) {
            $query->where('name', 'like', '%'.$term.'%')
                ->orWhere('document_number', 'like', '%'.$term.'%');

            if ($digits !== '') {
                $query->orWhere('document_number', 'like', '%'.$digits.'%')
                    ->orWhere('phone', 'like', '%'.$digits.'%');
            }
        });
    }

    /**
     * Productores que el usuario puede consultar: todos para quien los administra,
     * y solo el suyo para el resto.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        if (Gate::forUser($user)->allows('manage-producers')) {
            return;
        }

        $query->whereKey($user->producer_id ?? 0);
    }
}
