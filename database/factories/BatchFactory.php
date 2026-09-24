<?php

namespace Database\Factories;

use App\Enums\BatchStatus;
use App\Models\Batch;
use App\Models\Pond;
use App\Models\Species;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Batch>
 */
class BatchFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $number = fake()->unique()->numberBetween(1, 99999);

        return [
            'pond_id' => Pond::factory(),
            'species_id' => Species::factory(),
            'code' => 'L-'.$number,
            'name' => 'Lote '.$number,
            'stocked_at' => fake()->dateTimeBetween('-120 days', '-10 days')->format('Y-m-d'),
            'initial_quantity' => fake()->numberBetween(1000, 20000),
            'initial_average_weight_g' => fake()->randomFloat(2, 0.5, 5),
            'notes' => null,
        ];
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BatchStatus::Closed,
            'closed_at' => today()->subDay()->toDateString(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BatchStatus::Cancelled,
            'closed_at' => today()->subDay()->toDateString(),
        ]);
    }
}
