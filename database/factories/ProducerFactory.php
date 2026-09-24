<?php

namespace Database\Factories;

use App\Enums\DocumentType;
use App\Enums\PersonType;
use App\Models\Producer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Producer>
 */
class ProducerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'person_type' => PersonType::Natural,
            'name' => fake()->name(),
            'document_type' => DocumentType::CitizenId,
            'document_number' => (string) fake()->unique()->numberBetween(10000000, 1999999999),
            'phone' => '3'.fake()->numerify('#########'),
            'email' => fake()->unique()->safeEmail(),
            'municipality' => fake()->randomElement(['Neiva', 'Villavicencio', 'Palermo', 'Rivera', 'Garzón']),
            'department' => fake()->randomElement(['Huila', 'Meta', 'Tolima']),
            'is_active' => true,
        ];
    }

    public function juridical(): static
    {
        return $this->state(fn (array $attributes) => [
            'person_type' => PersonType::Juridical,
            'name' => fake()->company().' S.A.S.',
            'document_type' => DocumentType::TaxId,
            'document_number' => fake()->unique()->numerify('9########').'-'.fake()->randomDigit(),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
