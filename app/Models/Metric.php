<?php

namespace App\Models;

use App\Models\Concerns\IsCatalogItem;
use Database\Factories\MetricFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Parámetro de calidad del agua (temperatura, pH, oxígeno disuelto…).
 * El código es estable: lo usarán las lecturas, los sensores y la API, por eso no se edita.
 */
class Metric extends Model
{
    /** @use HasFactory<MetricFactory> */
    use HasFactory, IsCatalogItem;

    public const SEARCHABLE = ['name', 'code'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'name',
        'unit',
        'decimals',
        'min_value',
        'max_value',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'decimals' => 'integer',
            'min_value' => 'decimal:3',
            'max_value' => 'decimal:3',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<WaterQualityReading, $this>
     */
    public function waterQualityReadings(): HasMany
    {
        return $this->hasMany(WaterQualityReading::class);
    }

    /**
     * @return HasMany<AlertRule, $this>
     */
    public function alertRules(): HasMany
    {
        return $this->hasMany(AlertRule::class);
    }

    public function catalogLabel(): string
    {
        return $this->name;
    }

    /**
     * Da formato a un valor con los decimales y la unidad del parámetro.
     */
    public function format(float|string|null $value): string
    {
        if ($value === null) {
            return '—';
        }

        return trim(number_format((float) $value, $this->decimals, ',', '.').' '.$this->unit);
    }
}
