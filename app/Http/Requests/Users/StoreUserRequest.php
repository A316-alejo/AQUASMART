<?php

namespace App\Http\Requests\Users;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    use ValidatesUserInput;

    public function authorize(): bool
    {
        return $this->user()->can('create', User::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => $this->nameRules(),
            'email' => [...$this->emailRules(), Rule::unique(User::class, 'email')],
            'role' => ['required', ...$this->roleRules()],
            'producer_id' => $this->producerRules((string) $this->input('role')),
            'is_active' => ['required', 'boolean'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ];
    }
}
