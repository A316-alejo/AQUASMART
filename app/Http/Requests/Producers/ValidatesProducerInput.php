<?php

namespace App\Http\Requests\Producers;

use App\Enums\DocumentType;
use App\Enums\PersonType;
use App\Models\Producer;
use Closure;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

trait ValidatesProducerInput
{
    protected function prepareForValidation(): void
    {
        $normalized = [];

        if (is_string($this->input('name'))) {
            $normalized['name'] = Str::squish($this->input('name'));
        }

        if (is_string($this->input('document_number'))) {
            $normalized['document_number'] = Str::upper(preg_replace('/[\s.]+/', '', $this->input('document_number')));
        }

        if (is_string($this->input('phone'))) {
            $normalized['phone'] = preg_replace('/[\s().-]+/', '', $this->input('phone'));
        }

        if (is_string($this->input('email'))) {
            $normalized['email'] = Str::lower(trim($this->input('email')));
        }

        $this->merge($normalized);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function producerRules(?Producer $producer = null): array
    {
        return [
            'person_type' => ['required', Rule::enum(PersonType::class)],
            'name' => ['required', 'string', 'min:3', 'max:150'],
            'document_type' => [
                'required',
                Rule::enum(DocumentType::class),
                function (string $attribute, mixed $value, Closure $fail) {
                    if ($this->input('person_type') === PersonType::Juridical->value && $value !== DocumentType::TaxId->value) {
                        $fail('Una persona jurídica debe identificarse con NIT.');
                    }
                },
            ],
            'document_number' => [
                'required',
                'string',
                'max:20',
                Rule::unique(Producer::class, 'document_number')->ignore($producer),
                function (string $attribute, mixed $value, Closure $fail) {
                    $type = DocumentType::tryFrom((string) $this->input('document_type'));

                    if ($type && ! preg_match($type->pattern(), (string) $value)) {
                        $fail("El número de documento no es válido para {$type->label()}: debe tener {$type->formatHint()}.");
                    }
                },
            ],
            'phone' => ['required', 'string', 'regex:/^\+?[0-9]{7,15}$/'],
            'email' => ['nullable', 'string', 'email:rfc', 'max:255'],
            'municipality' => ['required', 'string', 'min:2', 'max:100'],
            'department' => ['required', 'string', Rule::in(config('aquasmart.departments'))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => 'El teléfono debe tener entre 7 y 15 dígitos y puede comenzar con +.',
            'document_number.unique' => 'Ya existe un productor registrado con este número de documento.',
        ];
    }
}
