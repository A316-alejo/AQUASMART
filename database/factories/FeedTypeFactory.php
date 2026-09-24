<?php

namespace Database\Factories;

use App\Models\FeedType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeedType>
 */
class FeedTypeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Alimento '.fake()->unique()->numberBetween(1, 99999),
            'brand' => null,
            'protein_pct' => fake()->randomElement([24, 30, 34, 38, 45]),
            'pellet_mm' => fake()->randomElement([1.5, 3, 4.5, 6]),
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
