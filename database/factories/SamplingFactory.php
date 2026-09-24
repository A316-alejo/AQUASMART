<?php

namespace Database\Factories;

use App\Models\Batch;
use App\Models\Sampling;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sampling>
 */
class SamplingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'batch_id' => Batch::factory(),
            'user_id' => null,
            'sampled_on' => today()->toDateString(),
            'sample_size' => fake()->numberBetween(20, 100),
            'average_weight_g' => fake()->randomFloat(2, 10, 500),
            'average_length_cm' => fake()->randomFloat(2, 5, 30),
            'notes' => null,
        ];
    }
}
