<?php

namespace Database\Factories;

use App\Enums\PondType;
use App\Models\Farm;
use App\Models\Pond;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pond>
 */
class PondFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $surface = fake()->randomFloat(2, 100, 2000);
        $depth = fake()->randomFloat(2, 1, 2);

        return [
            'farm_id' => Farm::factory(),
            'code' => 'E'.fake()->unique()->numberBetween(100, 99999),
            'name' => 'Estanque '.fake()->unique()->numberBetween(1, 99999),
            'type' => PondType::Geomembrane,
            'surface_m2' => $surface,
            'volume_m3' => round($surface * $depth, 2),
            'depth_m' => $depth,
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
