<?php

namespace Database\Factories;

use App\Enums\AlertStatus;
use App\Enums\WaterQualityStatus;
use App\Models\Alert;
use App\Models\Batch;
use App\Models\Metric;
use App\Models\WaterQualityReading;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Alert>
 *
 * batch_id, metric_id y water_quality_reading_id son independientes por defecto (cada uno con su
 * propio factory). Para escenarios donde deben coincidir (aislamiento, filtros), pásalos explícitos.
 */
class AlertFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'batch_id' => Batch::factory(),
            'metric_id' => Metric::factory(),
            'water_quality_reading_id' => WaterQualityReading::factory(),
            'value' => fake()->randomFloat(2, 1, 30),
            'severity' => WaterQualityStatus::Warning,
            'status' => AlertStatus::Pending,
        ];
    }

    public function critical(): static
    {
        return $this->state(fn (array $attributes) => ['severity' => WaterQualityStatus::Critical]);
    }

    public function acknowledged(): static
    {
        return $this->state(fn (array $attributes) => ['status' => AlertStatus::Acknowledged, 'acknowledged_at' => now()]);
    }

    public function resolved(): static
    {
        return $this->state(fn (array $attributes) => ['status' => AlertStatus::Resolved, 'resolved_at' => now()]);
    }
}
