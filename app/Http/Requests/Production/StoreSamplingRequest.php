<?php

namespace App\Http\Requests\Production;

use App\Services\BiomassCalculator;

class StoreSamplingRequest extends ProductionRecordRequest
{
    private ?int $liveFish = null;

    protected function prefix(): string
    {
        return 'sampling';
    }

    protected function prepareForValidation(): void
    {
        $this->normalize(['average_weight_g', 'average_length_cm']);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'sampling_date' => $this->dateRules(),
            // La muestra no puede ser mayor que la población viva del lote.
            'sample_size' => ['required', 'integer', 'min:1', 'max:'.$this->liveFish()],
            'average_weight_g' => ['required', 'numeric', 'gt:0', 'max:5000', 'decimal:0,2'],
            'average_length_cm' => ['nullable', 'numeric', 'gt:0', 'max:200', 'decimal:0,2'],
            'sampling_notes' => ['nullable', 'string', 'max:1000'],
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
            'sampling_date' => 'fecha',
            'sample_size' => 'peces muestreados',
            'average_weight_g' => 'peso promedio',
            'average_length_cm' => 'longitud promedio',
            'sampling_notes' => 'observaciones',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            'sample_size.max' => 'La muestra no puede ser mayor que los peces vivos del lote ('.number_format($this->liveFish(), 0, ',', '.').').',
        ];
    }

    public function record(): array
    {
        return [
            'sampled_on' => $this->validated('sampling_date'),
            'sample_size' => (int) $this->validated('sample_size'),
            'average_weight_g' => $this->validated('average_weight_g'),
            'average_length_cm' => $this->validated('average_length_cm'),
            'notes' => $this->validated('sampling_notes'),
        ];
    }
}
