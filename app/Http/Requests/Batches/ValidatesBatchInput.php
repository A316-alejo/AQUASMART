<?php

namespace App\Http\Requests\Batches;

use App\Models\Batch;
use App\Models\Species;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

trait ValidatesBatchInput
{
    protected function prepareForValidation(): void
    {
        $normalized = [];

        if (is_string($this->input('code'))) {
            $normalized['code'] = Str::upper(preg_replace('/\s+/', '', $this->input('code')));
        }

        if (is_string($this->input('name'))) {
            $normalized['name'] = Str::squish($this->input('name'));
        }

        if (is_string($this->input('notes'))) {
            $normalized['notes'] = trim($this->input('notes'));
        }

        // Admite separadores de miles en la cantidad ("12.000", "12 000").
        if (is_string($quantity = $this->input('initial_quantity'))) {
            $quantity = trim($quantity);
            $normalized['initial_quantity'] = preg_match('/^\d{1,3}([.\s]\d{3})+$/', $quantity)
                ? preg_replace('/[.\s]/', '', $quantity)
                : $quantity;
        }

        // Acepta coma decimal, habitual al escribir pesos en español.
        if (is_string($this->input('initial_average_weight_g'))) {
            $normalized['initial_average_weight_g'] = str_replace(',', '.', trim($this->input('initial_average_weight_g')));
        }

        $this->merge($normalized);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function batchRules(?Batch $batch = null): array
    {
        return [
            'code' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9-]+$/', Rule::unique(Batch::class)->ignore($batch)],
            'name' => ['required', 'string', 'min:2', 'max:100'],
            // Especie activa; al editar se conserva la especie actual aunque se haya desactivado después.
            'species_id' => [
                'required', 'integer',
                Rule::exists(Species::class, 'id')->where(fn (Builder $query) => $query
                    ->where('is_active', true)
                    ->when($batch?->species_id, fn (Builder $query, int $current) => $query->orWhere('id', $current))),
            ],
            'stocked_at' => ['required', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:today'],
            'initial_quantity' => ['required', 'integer', 'min:1', 'max:10000000'],
            'initial_average_weight_g' => ['required', 'numeric', 'gt:0', 'max:5000', 'decimal:0,2'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'pond_id' => 'estanque',
            'species_id' => 'especie',
            'code' => 'código',
            'name' => 'nombre del lote',
            'stocked_at' => 'fecha de siembra',
            'initial_quantity' => 'cantidad sembrada',
            'initial_average_weight_g' => 'peso promedio inicial',
            'notes' => 'observaciones',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => 'El código solo puede tener letras, números y guiones, sin espacios.',
            'code.unique' => 'Ya existe un lote con ese código.',
            'pond_id.exists' => 'El estanque seleccionado no existe o está inactivo.',
            'pond_id.prohibited' => 'El estanque de un lote no se puede cambiar.',
            'species_id.exists' => 'La especie seleccionada no existe o está inactiva.',
            'stocked_at.date_format' => 'La fecha de siembra no es válida.',
            'stocked_at.before_or_equal' => 'La fecha de siembra no puede ser posterior a hoy.',
            'stocked_at.after_or_equal' => 'La fecha de siembra no puede ser anterior al año 2000.',
        ];
    }
}
