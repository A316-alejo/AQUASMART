<?php

namespace App\Http\Requests\Producers;

use App\Models\Producer;
use Illuminate\Foundation\Http\FormRequest;

class StoreProducerRequest extends FormRequest
{
    use ValidatesProducerInput;

    public function authorize(): bool
    {
        return $this->user()->can('create', Producer::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            ...$this->producerRules(),
            'is_active' => ['required', 'boolean'],
        ];
    }
}
