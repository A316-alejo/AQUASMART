<?php

namespace App\Http\Requests\Ponds;

use App\Models\Pond;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePondRequest extends FormRequest
{
    use ValidatesPondInput;

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->pond());
    }

    /**
     * La finca no se cambia al editar (un estanque pertenece a una sola finca)
     * y el estado se cambia con las acciones de activar y desactivar.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'farm_id' => ['prohibited'],
            ...$this->pondRules($this->pond()->farm_id, $this->pond()),
        ];
    }

    private function pond(): Pond
    {
        return $this->route('pond');
    }
}
