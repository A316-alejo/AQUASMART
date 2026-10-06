<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountRecoveryPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_is_reset_and_old_sessions_are_invalidated(): void
    {
        $user = User::factory()->create();
        $oldRememberToken = $user->remember_token;

        $this->withSession([
            'recovery.verified_user_id' => $user->id,
            'recovery.verified_at' => now(),
        ])->post('/recuperar-cuenta/nueva-contrasena', [
            'password' => 'NuevaClave2026',
            'password_confirmation' => 'NuevaClave2026',
        ])->assertRedirect('/login')->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertTrue(Hash::check('NuevaClave2026', $user->password));
        $this->assertNotEquals($oldRememberToken, $user->remember_token);

        $this->post('/login', ['email' => $user->email, 'password' => 'NuevaClave2026'])
            ->assertRedirect('/dashboard');
    }

    public function test_old_password_no_longer_works_after_recovery(): void
    {
        $user = User::factory()->create();

        $this->withSession([
            'recovery.verified_user_id' => $user->id,
            'recovery.verified_at' => now(),
        ])->post('/recuperar-cuenta/nueva-contrasena', [
            'password' => 'NuevaClave2026',
            'password_confirmation' => 'NuevaClave2026',
        ]);

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_weak_passwords_are_rejected(): void
    {
        $user = User::factory()->create();

        $this->withSession([
            'recovery.verified_user_id' => $user->id,
            'recovery.verified_at' => now(),
        ])->post('/recuperar-cuenta/nueva-contrasena', [
            'password' => 'corta',
            'password_confirmation' => 'corta',
        ])->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_recovery_grant_expires_after_a_few_minutes(): void
    {
        $user = User::factory()->create();

        $this->withSession([
            'recovery.verified_user_id' => $user->id,
            'recovery.verified_at' => now()->subMinutes(30),
        ])->get('/recuperar-cuenta/nueva-contrasena')
            ->assertRedirect('/recuperar-cuenta');

        $this->withSession([
            'recovery.verified_user_id' => $user->id,
            'recovery.verified_at' => now()->subMinutes(30),
        ])->post('/recuperar-cuenta/nueva-contrasena', [
            'password' => 'NuevaClave2026',
            'password_confirmation' => 'NuevaClave2026',
        ])->assertRedirect('/recuperar-cuenta');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_recovery_session_cannot_be_forged_for_another_users_id(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();

        // El id solo puede llegar a la sesión a través de una verificación real (OTP/código/archivo);
        // nada en la petición permite al atacante indicar a qué usuario quiere restablecerle la contraseña
        // salvo falsificando su propia sesión verificada, que solo contiene su propio id tras verificarse.
        $this->withSession([
            'recovery.verified_user_id' => $attacker->id,
            'recovery.verified_at' => now(),
        ])->post('/recuperar-cuenta/nueva-contrasena', [
            'password' => 'NuevaClave2026',
            'password_confirmation' => 'NuevaClave2026',
        ]);

        $this->assertTrue(Hash::check('password', $owner->fresh()->password));
        $this->assertTrue(Hash::check('NuevaClave2026', $attacker->fresh()->password));
    }
}
