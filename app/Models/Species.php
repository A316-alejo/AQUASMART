<?php

namespace App\Models;

use App\Models\Concerns\IsCatalogItem;
use Database\Factories\SpeciesFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Species extends Model
{
    /** @use HasFactory<SpeciesFactory> */
    use HasFactory, IsCatalogItem;

    public const SEARCHABLE = ['common_name', 'scientific_name'];

    protected $table = 'species';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'common_name',
        'scientific_name',
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

    public function catalogLabel(): string
    {
        return $this->common_name;
    }
}
