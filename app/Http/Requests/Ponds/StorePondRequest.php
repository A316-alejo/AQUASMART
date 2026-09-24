<?php

namespace App\Http\Requests\Ponds;

use App\Models\Farm;
use App\Models\Pond;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePondRequest extends FormRequest
{
    use ValidatesPondInput;

    public function authorize(): bool
    {
        return $this->user()->can('create', Pond::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'farm_id' => ['required', 'integer', Rule::exists(Farm::class, 'id')->where('is_active', true)],
            ...$this->pondRules(is_numeric($this->input('farm_id')) ? (int) $this->input('farm_id') : null),
            'is_active' => ['required', 'boolean'],
        ];
    }
}
