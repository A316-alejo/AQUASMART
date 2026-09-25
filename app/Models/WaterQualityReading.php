<?php

namespace App\Models;

use Database\Factories\WaterQualityReadingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Medición de un parámetro de calidad del agua sobre un lote. `source` distingue el origen
 * (manual ahora; el paso de sensores enviará lecturas con la misma forma y source = 'sensor').
 */
class WaterQualityReading extends Model
{
    /** @use HasFactory<WaterQualityReadingFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'metric_id',
        'user_id',
        'measured_at',
        'value',
        'source',
        'notes',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'source' => 'manual',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'measured_at' => 'datetime',
            'value' => 'decimal:3',
        ];
    }

    /**
     * @return BelongsTo<Batch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    /**
     * @return BelongsTo<Metric, $this>
     */
    public function metric(): BelongsTo
    {
        return $this->belongsTo(Metric::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
