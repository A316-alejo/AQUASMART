<?php

namespace App\Http\Requests\Sensors;

use App\Models\Device;
use App\Models\Metric;
use App\Models\Pond;
use App\Models\Sensor;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

trait ValidatesSensorInput
{
    protected function prepareForValidation(): void
    {
        $normalized = [];

        if (is_string($this->input('code'))) {
            $normalized['code'] = Str::upper(preg_replace('/\s+/', '', $this->input('code')));
        }

        if (is_string($this->input('name'))) {
            $normalized['name'] = Str::squish($this->input('name'));
        }

        $this->merge($normalized);
    }

    /**
     * El estanque debe pertenecer a la misma finca que el dispositivo: un sensor nunca
     * mide un estanque de otra finca aunque el usuario conozca su id.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function sensorRules(Device $device, ?Sensor $sensor = null): array
    {
        return [
            'pond_id' => [
                'required', 'integer',
                Rule::exists(Pond::class, 'id')->where('farm_id', $device->farm_id)->where('is_active', true),
            ],
            'metric_id' => ['required', 'integer', Rule::exists(Metric::class, 'id')->where('is_active', true)],
            'code' => [
                'required', 'string', 'max:30', 'regex:/^[A-Z0-9-]+$/',
                Rule::unique(Sensor::class, 'code')->where('device_id', $device->id)->ignore($sensor),
            ],
            'name' => ['required', 'string', 'min:2', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'pond_id' => 'estanque',
            'metric_id' => 'parámetro',
            'code' => 'código',
            'name' => 'nombre del sensor',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => 'El código solo puede tener letras, números y guiones, sin espacios.',
            'code.unique' => 'Este dispositivo ya tiene un sensor con ese código.',
            'pond_id.exists' => 'El estanque seleccionado no existe, está inactivo o no pertenece a la finca del dispositivo.',
            'metric_id.exists' => 'El parámetro seleccionado no existe o está inactivo.',
        ];
    }
}
