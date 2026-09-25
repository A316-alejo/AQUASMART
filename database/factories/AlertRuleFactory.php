<?php

namespace Database\Factories;

use App\Models\AlertRule;
use App\Models\Farm;
use App\Models\Metric;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AlertRule>
 */
class AlertRuleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'farm_id' => Farm::factory(),
            'metric_id' => Metric::factory(),
            'warn_min' => null,
            'warn_max' => null,
            'crit_min' => null,
            'crit_max' => null,
            'enabled' => true,
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => ['enabled' => false]);
    }
}
