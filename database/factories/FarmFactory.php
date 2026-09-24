<?php

namespace Database\Factories;

use App\Models\Farm;
use App\Models\Producer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Farm>
 */
class FarmFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'producer_id' => Producer::factory(),
            'name' => 'Finca '.fake()->unique()->lastName(),
            'location' => 'Vereda '.fake()->lastName(),
            'municipality' => fake()->randomElement(['Neiva', 'Palermo', 'Rivera', 'Villavicencio', 'Garzón']),
            'department' => fake()->randomElement(['Huila', 'Meta', 'Tolima']),
            'latitude' => fake()->latitude(1, 5),
            'longitude' => fake()->longitude(-76, -73),
            'area_ha' => fake()->randomFloat(2, 1, 80),
            'timezone' => 'America/Bogota',
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
