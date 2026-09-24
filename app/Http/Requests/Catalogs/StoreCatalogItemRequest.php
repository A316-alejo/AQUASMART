<?php

namespace App\Http\Requests\Catalogs;

use App\Enums\Catalog;
use Illuminate\Foundation\Http\FormRequest;

class StoreCatalogItemRequest extends FormRequest
{
    use ValidatesCatalogInput;

    public function authorize(): bool
    {
        return $this->user()->can('create', $this->catalog()->model());
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            ...($this->catalog() === Catalog::Metrics ? $this->metricCodeRules() : []),
            ...$this->catalogRules(),
            'is_active' => ['required', 'boolean'],
        ];
    }
}
