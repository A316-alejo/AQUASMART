<?php

namespace App\Models;

use Database\Factories\FeedingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Evento de alimentación de un lote.
 */
class Feeding extends Model
{
    /** @use HasFactory<FeedingFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'feed_type_id',
        'user_id',
        'fed_on',
        'quantity_g',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fed_on' => 'date',
            'quantity_g' => 'decimal:2',
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
     * @return BelongsTo<FeedType, $this>
     */
    public function feedType(): BelongsTo
    {
        return $this->belongsTo(FeedType::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
