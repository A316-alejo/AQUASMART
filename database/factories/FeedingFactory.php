<?php

namespace Database\Factories;

use App\Models\Batch;
use App\Models\Feeding;
use App\Models\FeedType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Feeding>
 */
class FeedingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'batch_id' => Batch::factory(),
            'feed_type_id' => FeedType::factory(),
            'user_id' => null,
            'fed_on' => today()->toDateString(),
            'quantity_g' => fake()->randomFloat(2, 1000, 50000),
            'notes' => null,
        ];
    }
}
