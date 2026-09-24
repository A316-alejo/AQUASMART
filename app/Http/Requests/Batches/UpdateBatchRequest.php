<?php

namespace App\Http\Requests\Batches;

use App\Models\Batch;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBatchRequest extends FormRequest
{
    use ValidatesBatchInput;

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->batch());
    }

    /**
     * El estanque no se cambia después de la siembra; el estado cambia con las acciones de cerrar y cancelar.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'pond_id' => ['prohibited'],
            ...$this->batchRules($this->batch()),
        ];
    }

    private function batch(): Batch
    {
        return $this->route('batch');
    }
}
