<?php

namespace App\Http\Requests\Farms;

use App\Models\Farm;
use App\Models\Producer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFarmRequest extends FormRequest
{
    use ValidatesFarmInput;

    public function authorize(): bool
    {
        return $this->user()->can('create', Farm::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'producer_id' => ['required', 'integer', Rule::exists(Producer::class, 'id')->where('is_active', true)],
            ...$this->farmRules(is_numeric($this->input('producer_id')) ? (int) $this->input('producer_id') : null),
            'is_active' => ['required', 'boolean'],
        ];
    }
}
