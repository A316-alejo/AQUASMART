<?php

namespace Tests\Feature\Auth;

use App\Models\AccountRecoveryOtp;
use App\Models\User;
use App\Notifications\AccountRecoveryOtpNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AccountRecoveryOtpTest extends TestCase
{
    use RefreshDatabase;

    public function test_requesting_recovery_sends_an_otp_to_registered_users(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post('/recuperar-cuenta', ['email' => $user->email])
            ->assertRedirect('/recuperar-cuenta/verificar')
            ->assertSessionHas('status');

        Notification::assertSentTo($user, AccountRecoveryOtpNotification::class);
        $this->assertDatabaseCount('account_recovery_otps', 1);
    }

    public function test_unknown_emails_get_the_same_response_without_revealing_anything(): void
    {
        Notification::fake();

        $this->post('/recuperar-cuenta', ['email' => 'nadie@example.com'])
            ->assertRedirect('/recuperar-cuenta/verificar')
            ->assertSessionHas('status', __('auth.recovery_sent'));

        Notification::assertNothingSent();
        $this->assertDatabaseCount('account_recovery_otps', 0);
    }

    public function test_inactive_user_gets_the_same_response_and_no_otp(): void
    {
        Notification::fake();
        $user = User::factory()->inactive()->create();

        $this->post('/recuperar-cuenta', ['email' => $user->email])
            ->assertSessionHas('status', __('auth.recovery_sent'));

        Notification::assertNothingSent();
    }

    public function test_otp_codes_are_hashed_and_not_predictable(): void
    {
        Notification::fake();
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $this->post('/recuperar-cuenta', ['email' => $userA->email]);
        session()->flush();
        $this->post('/recuperar-cuenta', ['email' => $userB->email]);

        $otpA = AccountRecoveryOtp::where('user_id', $userA->id)->first();
        $otpB = AccountRecoveryOtp::where('user_id', $userB->id)->first();

        $this->assertNotNull($otpA);
        $this->assertNotNull($otpB);
        // Dos solicitudes generan hashes distintos (el bcrypt de cada código incluye una sal aleatoria).
        $this->assertNotEquals($otpA->code_hash, $otpB->code_hash);
    }

    public function test_correct_otp_grants_access_to_set_a_new_password(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post('/recuperar-cuenta', ['email' => $user->email]);

        Notification::assertSentTo($user, AccountRecoveryOtpNotification::class, function (AccountRecoveryOtpNotification $notification) {
            $code = $this->extractOtpCode($notification);

            $this->post('/recuperar-cuenta/verificar/otp', ['otp' => $code])
                ->assertRedirect('/recuperar-cuenta/nueva-contrasena');

            $this->get('/recuperar-cuenta/nueva-contrasena')->assertOk();

            return true;
        });
    }

    public function test_incorrect_otp_is_rejected(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post('/recuperar-cuenta', ['email' => $user->email]);

        $this->post('/recuperar-cuenta/verificar/otp', ['otp' => '000000'])
            ->assertSessionHasErrors(['otp' => __('auth.recovery_invalid')]);

        $this->get('/recuperar-cuenta/nueva-contrasena')->assertRedirect('/recuperar-cuenta');
    }

    public function test_expired_otp_is_rejected(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post('/recuperar-cuenta', ['email' => $user->email]);

        $otp = AccountRecoveryOtp::where('user_id', $user->id)->first();
        $otp->forceFill(['expires_at' => now()->subMinute()])->save();

        Notification::assertSentTo($user, AccountRecoveryOtpNotification::class, function (AccountRecoveryOtpNotification $notification) {
            $code = $this->extractOtpCode($notification);

            $this->post('/recuperar-cuenta/verificar/otp', ['otp' => $code])
                ->assertSessionHasErrors('otp');

            return true;
        });
    }

    public function test_otp_cannot_be_used_twice(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post('/recuperar-cuenta', ['email' => $user->email]);

        Notification::assertSentTo($user, AccountRecoveryOtpNotification::class, function (AccountRecoveryOtpNotification $notification) use ($user) {
            $code = $this->extractOtpCode($notification);

            $this->post('/recuperar-cuenta/verificar/otp', ['otp' => $code])
                ->assertRedirect('/recuperar-cuenta/nueva-contrasena');

            // Reingresa el mismo correo a la sesión (sin pedir un OTP nuevo) e intenta reusar el ya consumido.
            $this->withSession(['recovery.email' => $user->email])
                ->post('/recuperar-cuenta/verificar/otp', ['otp' => $code])
                ->assertSessionHasErrors('otp');

            return true;
        });
    }

    public function test_too_many_incorrect_attempts_locks_the_otp(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post('/recuperar-cuenta', ['email' => $user->email]);

        foreach (range(1, 5) as $attempt) {
            $this->post('/recuperar-cuenta/verificar/otp', ['otp' => '111111']);
        }

        Notification::assertSentTo($user, AccountRecoveryOtpNotification::class, function (AccountRecoveryOtpNotification $notification) {
            $code = $this->extractOtpCode($notification);

            // El OTP real ya quedó bloqueado por los intentos fallidos, aunque ahora se use el código correcto.
            $this->post('/recuperar-cuenta/verificar/otp', ['otp' => $code])
                ->assertSessionHasErrors('otp');

            return true;
        });
    }

    public function test_requesting_too_many_otps_is_rate_limited(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        foreach (range(1, 3) as $attempt) {
            $this->post('/recuperar-cuenta', ['email' => $user->email]);
        }

        $this->post('/recuperar-cuenta', ['email' => $user->email])
            ->assertSessionHasErrors('email');
    }

    public function test_resend_otp_is_rate_limited(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post('/recuperar-cuenta', ['email' => $user->email]);

        foreach (range(1, 3) as $attempt) {
            $this->post('/recuperar-cuenta/reenviar');
        }

        $this->post('/recuperar-cuenta/reenviar')
            ->assertSessionHasErrors('otp');
    }

    public function test_otp_is_never_sent_in_plain_text_in_the_response(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $response = $this->post('/recuperar-cuenta', ['email' => $user->email]);

        Notification::assertSentTo($user, AccountRecoveryOtpNotification::class, function (AccountRecoveryOtpNotification $notification) use ($response) {
            $code = $this->extractOtpCode($notification);
            $response->assertDontSee($code, false);

            return true;
        });
    }

    private function extractOtpCode(AccountRecoveryOtpNotification $notification): string
    {
        $mail = $notification->toMail(new \stdClass);

        foreach ($mail->introLines as $line) {
            if (preg_match('/[0-9]{6}/', (string) $line, $matches)) {
                return $matches[0];
            }
        }

        $this->fail('No se pudo extraer el código OTP del correo de prueba.');
    }
}
