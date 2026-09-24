<?php

namespace App\Models;

use Database\Factories\SamplingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Biometría: muestreo de peso (y longitud) promedio de un lote.
 */
class Sampling extends Model
{
    /** @use HasFactory<SamplingFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'sampled_on',
        'sample_size',
        'average_weight_g',
        'average_length_cm',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sampled_on' => 'date',
            'sample_size' => 'integer',
            'average_weight_g' => 'decimal:2',
            'average_length_cm' => 'decimal:2',
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
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
