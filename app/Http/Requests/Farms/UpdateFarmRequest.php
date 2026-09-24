<?php

namespace App\Http\Requests\Farms;

use App\Models\Farm;
use Illuminate\Foundation\Http\FormRequest;

class UpdateFarmRequest extends FormRequest
{
    use ValidatesFarmInput;

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->farm());
    }

    /**
     * El productor no se cambia al editar (una finca pertenece a un solo productor)
     * y el estado se cambia con las acciones de activar y desactivar.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'producer_id' => ['prohibited'],
            ...$this->farmRules($this->farm()->producer_id, $this->farm()),
        ];
    }

    private function farm(): Farm
    {
        return $this->route('farm');
    }
}
