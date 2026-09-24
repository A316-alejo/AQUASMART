<?php

namespace App\Http\Requests\Users;

use App\Models\User;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    use ValidatesUserInput;

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->editedUser());
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $user = $this->editedUser();
        $isSelf = $this->user()->is($user);

        return [
            'name' => $this->nameRules(),
            'email' => [...$this->emailRules(), Rule::unique(User::class, 'email')->ignore($user)],
            'role' => [
                'sometimes',
                'required',
                ...$this->roleRules(),
                function (string $attribute, mixed $value, Closure $fail) use ($user) {
                    if ($value !== $user->role->value && $this->user()->cannot('changeRole', $user)) {
                        $fail('No puedes cambiar tu propio rol.');
                    }
                },
            ],
            'producer_id' => $this->producerRules((string) $this->input('role', $user->role->value)),
            'password' => ['nullable', 'string', 'confirmed', Password::defaults()],
            'current_password' => $isSelf
                ? ['nullable', 'required_with:password', 'current_password']
                : ['exclude'],
        ];
    }

    public function editedUser(): User
    {
        return $this->route('user');
    }
}
