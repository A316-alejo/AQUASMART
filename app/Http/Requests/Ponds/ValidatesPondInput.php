<?php

namespace App\Http\Requests\Ponds;

use App\Enums\PondType;
use App\Models\Pond;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

trait ValidatesPondInput
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

        // Acepta coma decimal, habitual al escribir medidas en español.
        foreach (['surface_m2', 'volume_m3', 'depth_m'] as $field) {
            if (is_string($this->input($field))) {
                $normalized[$field] = str_replace(',', '.', trim($this->input($field)));
            }
        }

        $this->merge($normalized);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function pondRules(?int $farmId, ?Pond $pond = null): array
    {
        return [
            'code' => [
                'required', 'string', 'max:20', 'regex:/^[A-Z0-9-]+$/',
                Rule::unique(Pond::class, 'code')->where('farm_id', $farmId ?? 0)->ignore($pond),
            ],
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'type' => ['required', Rule::enum(PondType::class)],
            'surface_m2' => ['nullable', 'required_without:volume_m3', 'numeric', 'gt:0', 'max:1000000', 'decimal:0,2'],
            'volume_m3' => ['nullable', 'numeric', 'gt:0', 'max:10000000', 'decimal:0,2'],
            'depth_m' => ['nullable', 'numeric', 'gt:0', 'max:50', 'decimal:0,2'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'farm_id' => 'finca',
            'code' => 'código',
            'name' => 'nombre del estanque',
            'type' => 'tipo de estanque',
            'surface_m2' => 'superficie',
            'volume_m3' => 'volumen',
            'depth_m' => 'profundidad',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => 'El código solo puede tener letras, números y guiones, sin espacios.',
            'code.unique' => 'Esta finca ya tiene un estanque con ese código.',
            'surface_m2.required_without' => 'Registra al menos la superficie o el volumen del estanque.',
            'farm_id.exists' => 'La finca seleccionada no existe o está inactiva.',
        ];
    }
}
