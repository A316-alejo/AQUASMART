<?php

namespace App\Http\Requests\Users;

use App\Enums\Role;
use App\Models\Producer;
use Closure;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

trait ValidatesUserInput
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => Str::lower(trim($this->input('email')))]);
        }
    }

    /**
     * @return array<int, mixed>
     */
    protected function nameRules(): array
    {
        return ['required', 'string', 'min:3', 'max:120'];
    }

    /**
     * @return array<int, mixed>
     */
    protected function emailRules(): array
    {
        return ['required', 'string', 'email:rfc', 'max:255'];
    }

    /**
     * @return array<int, mixed>
     */
    protected function roleRules(): array
    {
        return [
            'string',
            Rule::enum(Role::class),
            function (string $attribute, mixed $value, Closure $fail) {
                $role = Role::tryFrom((string) $value);

                if ($role && Gate::forUser($this->user())->denies('assign-role', $role)) {
                    $fail('No puedes asignar un rol superior al tuyo.');
                }
            },
        ];
    }

    /**
     * Un productor debe pertenecer a un productor; un administrador no puede pertenecer a ninguno.
     *
     * @return array<int, mixed>
     */
    protected function producerRules(?string $role): array
    {
        return [
            Rule::requiredIf($role === Role::Producer->value),
            Rule::prohibitedIf($role === Role::Admin->value),
            'nullable',
            'integer',
            Rule::exists(Producer::class, 'id'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'producer_id.required' => 'Un usuario con rol Productor debe estar asociado a un productor.',
            'producer_id.prohibited' => 'Un administrador no puede estar asociado a un productor.',
        ];
    }
}
