<?php

namespace App\Http\Requests\Farms;

use App\Models\Farm;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

trait ValidatesFarmInput
{
    protected function prepareForValidation(): void
    {
        $normalized = [];

        if (is_string($this->input('name'))) {
            $normalized['name'] = Str::squish($this->input('name'));
        }

        // Acepta coma decimal, habitual al escribir coordenadas y áreas en español.
        foreach (['latitude', 'longitude', 'area_ha'] as $field) {
            if (is_string($this->input($field))) {
                $normalized[$field] = str_replace(',', '.', trim($this->input($field)));
            }
        }

        $this->merge($normalized);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function farmRules(?int $producerId, ?Farm $farm = null): array
    {
        return [
            'name' => [
                'required', 'string', 'min:3', 'max:150',
                Rule::unique(Farm::class, 'name')->where('producer_id', $producerId ?? 0)->ignore($farm),
            ],
            'location' => ['nullable', 'string', 'max:255'],
            'municipality' => ['required', 'string', 'min:2', 'max:100'],
            'department' => ['required', 'string', Rule::in(config('aquasmart.departments'))],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90', 'decimal:0,7'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180', 'decimal:0,7'],
            'area_ha' => ['nullable', 'numeric', 'gt:0', 'max:100000', 'decimal:0,2'],
            'timezone' => ['required', 'string', 'timezone:all'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'producer_id' => 'productor',
            'name' => 'nombre de la finca',
            'location' => 'vereda o dirección',
            'latitude' => 'latitud',
            'longitude' => 'longitud',
            'area_ha' => 'área',
            'timezone' => 'zona horaria',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'Este productor ya tiene una finca con ese nombre.',
            'producer_id.exists' => 'El productor seleccionado no existe o está inactivo.',
        ];
    }
}
