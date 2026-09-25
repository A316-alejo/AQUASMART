<?php

namespace App\Models;

use Database\Factories\AlertRuleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Umbrales de advertencia y crítico de un parámetro para una finca.
 *
 * Se separa a propósito del rango físico válido de Metric (min_value/max_value): ese descarta
 * lecturas imposibles al guardarlas; esto clasifica lecturas ya válidas como normal/advertencia/crítico
 * (ver App\Services\WaterQualityEvaluator). Sin una regla habilitada, una lectura se considera normal.
 */
class AlertRule extends Model
{
    /** @use HasFactory<AlertRuleFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'farm_id',
        'metric_id',
        'warn_min',
        'warn_max',
        'crit_min',
        'crit_max',
        'enabled',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'warn_min' => 'decimal:3',
            'warn_max' => 'decimal:3',
            'crit_min' => 'decimal:3',
            'crit_max' => 'decimal:3',
            'enabled' => 'boolean',
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
     * @return BelongsTo<Metric, $this>
     */
    public function metric(): BelongsTo
    {
        return $this->belongsTo(Metric::class);
    }
}
