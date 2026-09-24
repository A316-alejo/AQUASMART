<?php

namespace App\Http\Requests\Batches;

use App\Models\Batch;
use App\Models\Farm;
use App\Models\Pond;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBatchRequest extends FormRequest
{
    use ValidatesBatchInput;

    public function authorize(): bool
    {
        return $this->user()->can('create', Batch::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // Solo estanques activos de fincas activas.
            'pond_id' => [
                'required', 'integer',
                Rule::exists(Pond::class, 'id')->where(fn (Builder $query) => $query
                    ->where('is_active', true)
                    ->whereIn('farm_id', Farm::active()->select('id'))),
            ],
            ...$this->batchRules(),
        ];
    }
}
