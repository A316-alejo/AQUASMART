<?php

namespace App\Http\Requests\Production;

use App\Models\MortalityCause;
use App\Services\BiomassCalculator;
use Illuminate\Validation\Rule;

class StoreMortalityRequest extends ProductionRecordRequest
{
    private ?int $liveFish = null;

    protected function prefix(): string
    {
        return 'mortality';
    }

    protected function prepareForValidation(): void
    {
        $this->normalize([]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'mortality_date' => $this->dateRules(),
            'mortality_cause_id' => ['required', 'integer', Rule::exists(MortalityCause::class, 'id')->where('is_active', true)],
            // No puede superar los peces vivos: la mortalidad acumulada nunca excede la siembra.
            'mortality_quantity' => ['required', 'integer', 'min:1', 'max:'.$this->liveFish()],
            'mortality_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function liveFish(): int
    {
        return $this->liveFish ??= app(BiomassCalculator::class)->liveFish($this->batch());
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'mortality_date' => 'fecha',
            'mortality_cause_id' => 'causa',
            'mortality_quantity' => 'cantidad de peces muertos',
            'mortality_notes' => 'observaciones',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            'mortality_cause_id.exists' => 'La causa seleccionada no existe o está inactiva.',
            'mortality_quantity.max' => 'La cantidad supera los peces vivos del lote ('.number_format($this->liveFish(), 0, ',', '.').').',
        ];
    }

    public function record(): array
    {
        return [
            'recorded_on' => $this->validated('mortality_date'),
            'mortality_cause_id' => $this->validated('mortality_cause_id'),
            'quantity' => (int) $this->validated('mortality_quantity'),
            'notes' => $this->validated('mortality_notes'),
        ];
    }
}
