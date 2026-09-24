<?php

namespace App\Http\Requests\Catalogs;

use App\Enums\Catalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCatalogItemRequest extends FormRequest
{
    use ValidatesCatalogInput;

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->item());
    }

    /**
     * El estado se cambia con las acciones de activar y desactivar.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            ...($this->catalog() === Catalog::Metrics ? ['code' => ['prohibited']] : []),
            ...$this->catalogRules($this->item()),
        ];
    }

    private function item(): Model
    {
        return $this->route('item');
    }
}
