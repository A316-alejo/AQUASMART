<?php

namespace App\Models;

use Database\Factories\MortalityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro de peces muertos de un lote.
 */
class Mortality extends Model
{
    /** @use HasFactory<MortalityFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'mortality_cause_id',
        'user_id',
        'recorded_on',
        'quantity',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'recorded_on' => 'date',
            'quantity' => 'integer',
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
     * @return BelongsTo<MortalityCause, $this>
     */
    public function cause(): BelongsTo
    {
        return $this->belongsTo(MortalityCause::class, 'mortality_cause_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
