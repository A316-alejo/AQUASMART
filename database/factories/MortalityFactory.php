<?php

namespace Database\Factories;

use App\Models\Batch;
use App\Models\Mortality;
use App\Models\MortalityCause;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Mortality>
 */
class MortalityFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'batch_id' => Batch::factory(),
            'mortality_cause_id' => MortalityCause::factory(),
            'user_id' => null,
            'recorded_on' => today()->toDateString(),
            'quantity' => fake()->numberBetween(1, 50),
            'notes' => null,
        ];
    }
}
