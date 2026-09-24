<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_link_is_sent_to_registered_users(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post('/recuperar-contrasena', ['email' => $user->email])
            ->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_unknown_emails_get_the_same_response(): void
    {
        Notification::fake();

        $this->post('/recuperar-contrasena', ['email' => 'nadie@example.com'])
            ->assertSessionHas('status', __('passwords.sent'))
            ->assertSessionHasNoErrors();

        Notification::assertNothingSent();
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post('/recuperar-contrasena', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            $this->get('/restablecer-contrasena/'.$notification->token)->assertOk();

            $this->post('/restablecer-contrasena', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'NuevaClave2026',
                'password_confirmation' => 'NuevaClave2026',
            ])->assertRedirect('/login')->assertSessionHasNoErrors();

            return true;
        });

        $this->assertTrue(Hash::check('NuevaClave2026', $user->fresh()->password));
    }

    public function test_weak_passwords_are_rejected(): void
    {
        $user = User::factory()->create();

        $this->post('/restablecer-contrasena', [
            'token' => 'token',
            'email' => $user->email,
            'password' => 'corta',
            'password_confirmation' => 'corta',
        ])->assertSessionHasErrors('password');
    }
}
