<?php

namespace Tests\Feature\Api\V1;

use App\Enums\Role;
use App\Models\Farm;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Autenticación por token Sanctum para React (/api/v1/auth/*) y datos del formulario de estanques.
 */
class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_registro_crea_usuario_de_consulta_sin_devolver_token(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Ana Pérez', 'email' => 'ana@example.com', 'password' => 'secreta-123',
            'role' => 'admin', // debe ignorarse
        ])->assertCreated()->assertJsonMissingPath('token');

        $this->assertDatabaseHas('users', ['email' => 'ana@example.com', 'role' => 'consulta']);
    }

    public function test_registro_rechaza_correo_repetido_y_clave_corta(): void
    {
        $user = User::factory()->role(Role::Admin)->create();

        $this->postJson('/api/v1/auth/register', ['name' => 'Ana', 'email' => $user->email, 'password' => '123'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_login_devuelve_un_token_que_sirve_como_bearer(): void
    {
        $user = User::factory()->role(Role::Admin)->create(['password' => 'secreta-123']);

        $token = $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'secreta-123'])
            ->assertOk()->json('token');

        $this->withToken($token)->getJson('/api/v1/auth/me')
            ->assertOk()->assertJsonPath('data.email', $user->email)->assertJsonMissingPath('data.password');
    }

    public function test_login_con_credenciales_invalidas_responde_401(): void
    {
        $user = User::factory()->role(Role::Admin)->create();

        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'incorrecta'])
            ->assertUnauthorized()->assertJsonPath('message', 'Credenciales inválidas');
    }

    public function test_usuario_inactivo_no_obtiene_token(): void
    {
        $user = User::factory()->role(Role::Admin)->create(['password' => 'secreta-123', 'is_active' => false]);

        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'secreta-123'])
            ->assertForbidden()->assertJsonMissingPath('token');
    }

    public function test_sin_token_la_api_responde_401_json(): void
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->getJson('/api/v1/ponds')->assertUnauthorized();
        $this->get('/api/v1/ponds')->assertUnauthorized()->assertJsonStructure(['message']);
    }

    public function test_logout_revoca_el_token(): void
    {
        $user = User::factory()->role(Role::Admin)->create(['password' => 'secreta-123']);
        $token = $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'secreta-123'])->json('token');

        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertNoContent();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_un_usuario_desactivado_con_token_vigente_recibe_403(): void
    {
        $user = User::factory()->role(Role::Admin)->create(['password' => 'secreta-123']);
        $token = $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'secreta-123'])->json('token');
        $user->update(['is_active' => false]);

        $this->withToken($token)->getJson('/api/v1/ponds')->assertForbidden();
    }

    public function test_actualizar_perfil_solo_cambia_nombre_y_correo(): void
    {
        $user = User::factory()->role(Role::Viewer)->create();

        $this->actingAs($user, 'sanctum')->putJson('/api/v1/auth/me', [
            'name' => 'Nuevo Nombre', 'email' => 'nuevo@example.com', 'role' => 'admin',
        ])->assertOk()->assertJsonPath('data.name', 'Nuevo Nombre')->assertJsonPath('data.role', 'consulta');
    }

    public function test_options_devuelve_solo_fincas_visibles_y_tipos(): void
    {
        $producer = Producer::factory()->create();
        $mine = Farm::factory()->for($producer)->create();
        Farm::factory()->for(Producer::factory()->create())->create();
        $user = User::factory()->role(Role::Producer)->forProducer($producer)->create();

        $this->actingAs($user)->getJson('/api/v1/ponds/options')
            ->assertOk()
            ->assertJsonCount(1, 'data.farms')
            ->assertJsonPath('data.farms.0.id', $mine->id)
            ->assertJsonPath('data.types.geomembrana', 'Geomembrana');
    }
}
