<?php

namespace Database\Factories;

use App\Models\Batch;
use App\Models\Metric;
use App\Models\WaterQualityReading;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WaterQualityReading>
 */
class WaterQualityReadingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'batch_id' => Batch::factory(),
            'metric_id' => Metric::factory(),
            'user_id' => null,
            'measured_at' => now(),
            'value' => fake()->randomFloat(2, 1, 30),
            'source' => 'manual',
            'notes' => null,
        ];
    }
}
