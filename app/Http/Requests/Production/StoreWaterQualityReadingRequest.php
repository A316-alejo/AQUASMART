<?php

namespace App\Http\Requests\Production;

use App\Models\Metric;
use Closure;
use Illuminate\Validation\Rule;

class StoreWaterQualityReadingRequest extends ProductionRecordRequest
{
    protected function prefix(): string
    {
        return 'water_quality';
    }

    protected function prepareForValidation(): void
    {
        $this->normalize(['water_quality_value']);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'water_quality_date' => $this->dateTimeRules(),
            'water_quality_metric_id' => ['required', 'integer', Rule::exists(Metric::class, 'id')->where('is_active', true)],
            // El rango físico válido del parámetro se exige aparte (no son los umbrales de alerta).
            'water_quality_value' => [
                'required', 'numeric',
                function (string $attribute, mixed $value, Closure $fail) {
                    $metric = Metric::find($this->input('water_quality_metric_id'));

                    if (! $metric || ! is_numeric($value)) {
                        return;
                    }

                    if ($error = $metric->physicalRangeError((float) $value)) {
                        $fail($error);
                    }
                },
            ],
            'water_quality_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'water_quality_date' => 'fecha y hora',
            'water_quality_metric_id' => 'parámetro',
            'water_quality_value' => 'valor',
            'water_quality_notes' => 'observaciones',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'water_quality_date.date_format' => 'La fecha y hora no son válidas.',
            'water_quality_date.after_or_equal' => 'La fecha no puede ser anterior a la siembra del lote ('.$this->batch()->stocked_at->format('d/m/Y').').',
            'water_quality_date.before_or_equal' => 'La fecha no puede ser posterior al momento actual.',
            'water_quality_metric_id.exists' => 'El parámetro seleccionado no existe o está inactivo.',
        ];
    }

    public function record(): array
    {
        return [
            'metric_id' => $this->validated('water_quality_metric_id'),
            'measured_at' => str_replace('T', ' ', (string) $this->validated('water_quality_date')),
            'value' => $this->validated('water_quality_value'),
            'notes' => $this->validated('water_quality_notes'),
        ];
    }
}
