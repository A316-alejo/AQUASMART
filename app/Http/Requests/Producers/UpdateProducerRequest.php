<?php

namespace App\Http\Requests\Producers;

use App\Models\Producer;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProducerRequest extends FormRequest
{
    use ValidatesProducerInput;

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->producer());
    }

    /**
     * El estado se cambia con las acciones de activar y desactivar, que piden confirmación.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return $this->producerRules($this->producer());
    }

    private function producer(): Producer
    {
        return $this->route('producer');
    }
}
