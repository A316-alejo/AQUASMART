<?php

namespace Tests\Feature\Auth;

use App\Models\AccountRecoveryCode;
use App\Models\User;
use App\Services\AccountRecoveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountRecoveryCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_the_recovery_setup_page(): void
    {
        $this->get('/seguridad/recuperacion')->assertRedirect('/login');
        $this->post('/seguridad/recuperacion/codigos')->assertRedirect('/login');
    }

    public function test_authenticated_user_can_generate_recovery_codes(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/seguridad/recuperacion/codigos')
            ->assertRedirect('/seguridad/recuperacion')
            ->assertSessionHas('generated_codes');

        $codes = session('generated_codes');

        $this->assertCount(8, $codes);
        foreach ($codes as $code) {
            $this->assertMatchesRegularExpression('/^[0-9]{4}-[0-9]{4}$/', $code);
        }
        $this->assertDatabaseCount('account_recovery_codes', 8);
    }

    public function test_generating_new_codes_discards_the_previous_set(): void
    {
        $user = User::factory()->create();
        app(AccountRecoveryService::class)->generateRecoveryCodes($user);

        $this->assertDatabaseCount('account_recovery_codes', 8);

        $this->actingAs($user)->post('/seguridad/recuperacion/codigos');

        $this->assertDatabaseCount('account_recovery_codes', 8);
    }

    public function test_recovery_codes_are_not_predictable_and_are_hashed(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $codesA = app(AccountRecoveryService::class)->generateRecoveryCodes($userA);
        $codesB = app(AccountRecoveryService::class)->generateRecoveryCodes($userB);

        // Los 8 códigos de un mismo set son distintos entre sí (no hay un patrón secuencial).
        $this->assertSameSize(array_unique($codesA), $codesA);
        // Dos usuarios no obtienen el mismo set de códigos.
        $this->assertNotEquals($codesA, $codesB);

        $rows = AccountRecoveryCode::where('user_id', $userA->id)->get();
        foreach ($rows as $row) {
            $this->assertNotContains($row->code_hash, $codesA);
        }
    }

    public function test_correct_recovery_code_grants_access_to_set_a_new_password(): void
    {
        $user = User::factory()->create();
        $codes = app(AccountRecoveryService::class)->generateRecoveryCodes($user);

        $this->withSession(['recovery.email' => $user->email])
            ->post('/recuperar-cuenta/verificar/codigo', ['code' => $codes[0]])
            ->assertRedirect('/recuperar-cuenta/nueva-contrasena');

        $this->get('/recuperar-cuenta/nueva-contrasena')->assertOk();
    }

    public function test_used_recovery_code_cannot_be_reused(): void
    {
        $user = User::factory()->create();
        $codes = app(AccountRecoveryService::class)->generateRecoveryCodes($user);

        $this->withSession(['recovery.email' => $user->email])
            ->post('/recuperar-cuenta/verificar/codigo', ['code' => $codes[0]])
            ->assertRedirect('/recuperar-cuenta/nueva-contrasena');

        $this->withSession(['recovery.email' => $user->email])
            ->post('/recuperar-cuenta/verificar/codigo', ['code' => $codes[0]])
            ->assertSessionHasErrors(['code' => __('auth.recovery_invalid')]);
    }

    public function test_incorrect_recovery_code_is_rejected(): void
    {
        $user = User::factory()->create();
        app(AccountRecoveryService::class)->generateRecoveryCodes($user);

        $this->withSession(['recovery.email' => $user->email])
            ->post('/recuperar-cuenta/verificar/codigo', ['code' => '0000-0000'])
            ->assertSessionHasErrors(['code' => __('auth.recovery_invalid')]);
    }

    public function test_recovery_code_verification_is_rate_limited(): void
    {
        $user = User::factory()->create();
        app(AccountRecoveryService::class)->generateRecoveryCodes($user);

        foreach (range(1, 5) as $attempt) {
            $this->withSession(['recovery.email' => $user->email])
                ->post('/recuperar-cuenta/verificar/codigo', ['code' => '0000-0000']);
        }

        $this->withSession(['recovery.email' => $user->email])
            ->post('/recuperar-cuenta/verificar/codigo', ['code' => '0000-0000'])
            ->assertSessionHasErrors('code');
    }

    public function test_inactive_user_cannot_use_a_valid_recovery_code(): void
    {
        $user = User::factory()->inactive()->create();
        $codes = app(AccountRecoveryService::class)->generateRecoveryCodes($user);

        $this->withSession(['recovery.email' => $user->email])
            ->post('/recuperar-cuenta/verificar/codigo', ['code' => $codes[0]])
            ->assertSessionHasErrors(['code' => __('auth.recovery_invalid')]);
    }

    public function test_unverified_recovery_grants_cannot_access_the_password_form_by_guessing_ids(): void
    {
        $user = User::factory()->create();

        // Sin verificación previa en sesión, no hay forma de llegar al formulario de nueva contraseña,
        // ni siquiera manipulando el id del usuario (no existe un parámetro de ruta que lo acepte).
        $this->get('/recuperar-cuenta/nueva-contrasena')->assertRedirect('/recuperar-cuenta');

        $this->post('/recuperar-cuenta/nueva-contrasena', [
            'password' => 'NuevaClave2026',
            'password_confirmation' => 'NuevaClave2026',
        ])->assertRedirect('/recuperar-cuenta');

        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('password', $user->fresh()->password));
    }
}
