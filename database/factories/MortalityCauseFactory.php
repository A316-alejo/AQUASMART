<?php

namespace Database\Factories;

use App\Models\MortalityCause;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MortalityCause>
 */
class MortalityCauseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Causa '.fake()->unique()->numberBetween(1, 99999),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
