<?php

namespace App\Http\Requests\Catalogs;

use App\Enums\Catalog;
use App\Models\FeedType;
use App\Models\Metric;
use App\Models\MortalityCause;
use App\Models\Species;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

trait ValidatesCatalogInput
{
    protected function catalog(): Catalog
    {
        return $this->route('catalog');
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach (['common_name', 'scientific_name', 'name', 'brand', 'unit'] as $field) {
            if (is_string($this->input($field))) {
                $normalized[$field] = Str::squish($this->input($field));
            }
        }

        if (is_string($this->input('code'))) {
            $normalized['code'] = Str::lower(trim($this->input('code')));
        }

        // Acepta coma decimal, habitual al escribir cantidades en español.
        foreach (['protein_pct', 'pellet_mm', 'min_value', 'max_value'] as $field) {
            if (is_string($this->input($field))) {
                $normalized[$field] = str_replace(',', '.', trim($this->input($field)));
            }
        }

        $this->merge($normalized);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function catalogRules(?Model $item = null): array
    {
        return match ($this->catalog()) {
            Catalog::Species => [
                'common_name' => ['required', 'string', 'min:2', 'max:100', Rule::unique(Species::class)->ignore($item)],
                'scientific_name' => ['nullable', 'string', 'min:3', 'max:150'],
            ],
            Catalog::FeedTypes => [
                'name' => [
                    'required', 'string', 'min:2', 'max:100',
                    Rule::unique(FeedType::class)->ignore($item)->where(fn (Builder $query) => $this->filled('brand')
                        ? $query->where('brand', $this->input('brand'))
                        : $query->whereNull('brand')),
                ],
                'brand' => ['nullable', 'string', 'max:100'],
                'protein_pct' => ['required', 'numeric', 'gt:0', 'max:100', 'decimal:0,2'],
                'pellet_mm' => ['nullable', 'numeric', 'gt:0', 'max:50', 'decimal:0,2'],
            ],
            Catalog::MortalityCauses => [
                'name' => ['required', 'string', 'min:2', 'max:100', Rule::unique(MortalityCause::class)->ignore($item)],
            ],
            Catalog::Metrics => [
                'name' => ['required', 'string', 'min:2', 'max:100', Rule::unique(Metric::class)->ignore($item)],
                'unit' => ['nullable', 'string', 'max:20'],
                'decimals' => ['required', 'integer', 'between:0,4'],
                'min_value' => ['nullable', 'numeric', 'between:-1000000,1000000', 'decimal:0,3'],
                'max_value' => array_filter([
                    'nullable', 'numeric', 'between:-1000000,1000000', 'decimal:0,3',
                    is_numeric($this->input('min_value')) ? 'gt:min_value' : null,
                ]),
            ],
        };
    }

    /**
     * El código de un parámetro se define al crearlo: lo referencian las lecturas y los sensores.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function metricCodeRules(): array
    {
        return [
            'code' => ['required', 'string', 'min:2', 'max:40', 'regex:/^[a-z][a-z0-9_]*$/', Rule::unique(Metric::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'common_name' => 'nombre común',
            'scientific_name' => 'nombre científico',
            'name' => 'nombre',
            'brand' => 'marca',
            'protein_pct' => 'proteína',
            'pellet_mm' => 'tamaño del pellet',
            'code' => 'código',
            'unit' => 'unidad',
            'decimals' => 'decimales',
            'min_value' => 'valor mínimo',
            'max_value' => 'valor máximo',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $catalog = $this->catalog();

        return [
            'common_name.unique' => 'Ya existe una especie con ese nombre común.',
            'name.unique' => match ($catalog) {
                Catalog::FeedTypes => 'Ya existe un tipo de alimento con ese nombre y marca.',
                default => 'Ya existe '.$catalog->gender('un', 'una').' '.$catalog->singular().' con ese nombre.',
            },
            'code.regex' => 'El código debe empezar por una letra y solo puede tener minúsculas, números y guiones bajos.',
            'code.unique' => 'Ya existe un parámetro con ese código.',
            'code.prohibited' => 'El código de un parámetro no se puede cambiar.',
            'max_value.gt' => 'El valor máximo debe ser mayor que el valor mínimo.',
        ];
    }
}
