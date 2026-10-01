<?php

namespace App\Http\Requests\Devices;

use App\Models\Device;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDeviceRequest extends FormRequest
{
    use ValidatesDeviceInput;

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->device());
    }

    /**
     * La finca no se cambia al editar y el estado se cambia con activar/desactivar.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'farm_id' => ['prohibited'],
            ...$this->deviceRules($this->device()),
        ];
    }

    private function device(): Device
    {
        return $this->route('device');
    }
}
