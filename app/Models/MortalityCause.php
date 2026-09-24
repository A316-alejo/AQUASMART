<?php

namespace App\Models;

use App\Models\Concerns\IsCatalogItem;
use Database\Factories\MortalityCauseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MortalityCause extends Model
{
    /** @use HasFactory<MortalityCauseFactory> */
    use HasFactory, IsCatalogItem;

    public const SEARCHABLE = ['name'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
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
     * @return HasMany<Mortality, $this>
     */
    public function mortalities(): HasMany
    {
        return $this->hasMany(Mortality::class);
    }

    public function catalogLabel(): string
    {
        return $this->name;
    }
}
