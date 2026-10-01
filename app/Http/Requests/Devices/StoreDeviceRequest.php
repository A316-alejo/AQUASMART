<?php

namespace App\Http\Requests\Devices;

use App\Models\Device;
use App\Models\Farm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDeviceRequest extends FormRequest
{
    use ValidatesDeviceInput;

    public function authorize(): bool
    {
        return $this->user()->can('create', Device::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'farm_id' => ['required', 'integer', Rule::exists(Farm::class, 'id')->where('is_active', true)],
            ...$this->deviceRules(),
            'is_active' => ['required', 'boolean'],
        ];
    }
}
