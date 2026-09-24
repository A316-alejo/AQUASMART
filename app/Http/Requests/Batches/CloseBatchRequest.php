<?php

namespace App\Http\Requests\Batches;

use App\Models\Batch;
use Illuminate\Foundation\Http\FormRequest;

class CloseBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('close', $this->batch());
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'closed_at' => [
                'required', 'date_format:Y-m-d',
                'after_or_equal:'.$this->batch()->stocked_at->toDateString(),
                'before_or_equal:today',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['closed_at' => 'fecha de cierre'];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'closed_at.date_format' => 'La fecha de cierre no es válida.',
            'closed_at.after_or_equal' => 'La fecha de cierre no puede ser anterior a la siembra ('.$this->batch()->stocked_at->format('d/m/Y').').',
            'closed_at.before_or_equal' => 'La fecha de cierre no puede ser posterior a hoy.',
        ];
    }

    private function batch(): Batch
    {
        return $this->route('batch');
    }
}
