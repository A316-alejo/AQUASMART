<?php

namespace App\Http\Requests\Devices;

use App\Enums\DeviceType;
use App\Models\Device;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

trait ValidatesDeviceInput
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
     * @return array<string, array<int, mixed>>
     */
    protected function deviceRules(?Device $device = null): array
    {
        return [
            'code' => [
                'required', 'string', 'max:30', 'regex:/^[A-Z0-9-]+$/',
                Rule::unique(Device::class, 'code')->ignore($device),
            ],
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'type' => ['required', Rule::enum(DeviceType::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'farm_id' => 'finca',
            'code' => 'código',
            'name' => 'nombre del dispositivo',
            'type' => 'tipo de dispositivo',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => 'El código solo puede tener letras, números y guiones, sin espacios.',
            'code.unique' => 'Ya existe un dispositivo con ese código.',
            'farm_id.exists' => 'La finca seleccionada no existe o está inactiva.',
        ];
    }
}
