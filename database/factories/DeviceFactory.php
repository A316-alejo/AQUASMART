<?php

namespace Database\Factories;

use App\Enums\DeviceType;
use App\Models\Farm;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\Device>
 */
class DeviceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $number = fake()->unique()->numberBetween(1, 99999);

        return [
            'farm_id' => Farm::factory(),
            'name' => 'Dispositivo '.$number,
            'code' => 'DEV-'.$number,
            'type' => DeviceType::Esp32,
            // Las pruebas que necesitan el token en texto plano lo generan con $device->generateToken().
            'token_hash' => hash('sha256', Str::random(48)),
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
