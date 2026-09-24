<?php

namespace App\Http\Requests\Farms;

use App\Enums\Role;
use App\Models\Farm;
use App\Models\User;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFarmAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageAssignments', $this->farm());
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $farm = $this->farm();

        return [
            'user_id' => [
                'required',
                'integer',
                Rule::exists(User::class, 'id'),
                function (string $attribute, mixed $value, Closure $fail) use ($farm) {
                    $user = User::find($value);

                    if (! $user) {
                        return;
                    }

                    if (! $user->hasRole(Role::Technician, Role::Viewer)) {
                        $fail('Solo se pueden asignar usuarios con rol Técnico o Consulta; los productores ya ven las fincas de su productor.');
                    } elseif (! $user->is_active) {
                        $fail('La cuenta seleccionada está inactiva.');
                    } elseif ($user->producer_id !== null && $user->producer_id !== $farm->producer_id) {
                        $fail('La cuenta pertenece a otro productor y no puede asignarse a esta finca.');
                    } elseif ($farm->users()->whereKey($user->getKey())->exists()) {
                        $fail('La cuenta ya está asignada a esta finca.');
                    }
                },
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['user_id' => 'usuario'];
    }

    public function farm(): Farm
    {
        return $this->route('farm');
    }
}
