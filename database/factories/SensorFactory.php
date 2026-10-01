<?php

namespace Database\Factories;

use App\Models\Device;
use App\Models\Metric;
use App\Models\Pond;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Sensor>
 */
class SensorFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $number = fake()->unique()->numberBetween(1, 99999);

        return [
            'device_id' => Device::factory(),
            'pond_id' => Pond::factory(),
            'metric_id' => Metric::factory(),
            'name' => 'Sensor '.$number,
            'code' => 'S-'.$number,
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
