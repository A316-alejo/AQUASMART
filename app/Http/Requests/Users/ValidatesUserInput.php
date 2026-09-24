<?php

namespace App\Http\Requests\Users;

use App\Enums\Role;
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
}
