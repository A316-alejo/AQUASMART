<?php

namespace App\Http\Requests\Sensors;

use App\Models\Device;
use App\Models\Sensor;
use Illuminate\Foundation\Http\FormRequest;

class StoreSensorRequest extends FormRequest
{
    use ValidatesSensorInput;

    public function authorize(): bool
    {
        return $this->user()->can('create', Sensor::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            ...$this->sensorRules($this->device()),
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function device(): Device
    {
        return $this->route('device');
    }
}
