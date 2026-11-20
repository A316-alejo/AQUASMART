<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

/**
 * Autenticación por token Sanctum para el frontend React (/api/v1/auth/*).
 *
 * El registro no devuelve token: el usuario debe iniciar sesión después. El token se entrega una
 * sola vez en el login (en la base de datos queda hasheado) y React lo envía como Bearer.
 */
class AuthController extends Controller
{
    /**
     * Registro público: crea siempre un usuario de solo lectura (rol consulta) sin productor ni
     * fincas. Un administrador debe asignarle fincas; nadie elige su propio rol desde aquí.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = new User($request->validated());
        $user->role = Role::Viewer;
        $user->is_active = true;
        $user->save();

        return response()->json(['message' => 'Usuario registrado', 'userId' => $user->id], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Credenciales inválidas'], 401);
        }

        if (! $user->canAccessSystem()) {
            return response()->json(['message' => __($user->is_active ? 'auth.producer_inactive' : 'auth.inactive')], 403);
        }

        return response()->json([
            'token' => $user->createToken('react')->plainTextToken,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json(['data' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
        ]]);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class, 'email')->ignore($user->id)],
        ]);

        $user->update($data); // solo name y email: nunca rol ni contraseña

        return $this->me($request);
    }

    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }
}
