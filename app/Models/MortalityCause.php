<?php

namespace App\Models;

use App\Models\Concerns\IsCatalogItem;
use Database\Factories\MortalityCauseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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

    public function catalogLabel(): string
    {
        return $this->name;
    }
}
