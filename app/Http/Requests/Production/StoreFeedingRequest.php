<?php

namespace App\Http\Requests\Production;

use App\Models\FeedType;
use Illuminate\Validation\Rule;

class StoreFeedingRequest extends ProductionRecordRequest
{
    protected function prefix(): string
    {
        return 'feeding';
    }

    protected function prepareForValidation(): void
    {
        $this->normalize(['feeding_quantity_g']);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'feeding_date' => $this->dateRules(),
            'feed_type_id' => ['required', 'integer', Rule::exists(FeedType::class, 'id')->where('is_active', true)],
            // Hasta 10 toneladas por evento.
            'feeding_quantity_g' => ['required', 'numeric', 'gt:0', 'max:10000000', 'decimal:0,2'],
            'feeding_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'feeding_date' => 'fecha',
            'feed_type_id' => 'tipo de alimento',
            'feeding_quantity_g' => 'cantidad de alimento',
            'feeding_notes' => 'observaciones',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            'feed_type_id.exists' => 'El tipo de alimento seleccionado no existe o está inactivo.',
        ];
    }

    public function record(): array
    {
        return [
            'fed_on' => $this->validated('feeding_date'),
            'feed_type_id' => $this->validated('feed_type_id'),
            'quantity_g' => $this->validated('feeding_quantity_g'),
            'notes' => $this->validated('feeding_notes'),
        ];
    }
}
