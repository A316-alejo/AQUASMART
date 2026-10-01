<?php

namespace App\Http\Requests\Sensors;

use App\Models\Device;
use App\Models\Sensor;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSensorRequest extends FormRequest
{
    use ValidatesSensorInput;

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->sensor());
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return $this->sensorRules($this->device(), $this->sensor());
    }

    public function device(): Device
    {
        return $this->route('device');
    }

    public function sensor(): Sensor
    {
        return $this->route('sensor');
    }
}
