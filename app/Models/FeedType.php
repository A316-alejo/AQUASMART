<?php

namespace App\Models;

use App\Models\Concerns\IsCatalogItem;
use Database\Factories\FeedTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FeedType extends Model
{
    /** @use HasFactory<FeedTypeFactory> */
    use HasFactory, IsCatalogItem;

    public const SEARCHABLE = ['name', 'brand'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'brand',
        'protein_pct',
        'pellet_mm',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'protein_pct' => 'decimal:2',
            'pellet_mm' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function catalogLabel(): string
    {
        return $this->brand ? "{$this->name} ({$this->brand})" : $this->name;
    }
}
