<?php

namespace Tests\Feature\Auth;

use App\Models\AccountRecoveryOtp;
use App\Models\User;
use App\Services\AccountRecoveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\RawMessage;
use Tests\TestCase;

class AccountRecoveryMailFailureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Transporte que simula un servidor de correo caído (por ejemplo, Mailpit detenido).
        Mail::extend('failing', fn () => new class implements TransportInterface
        {
            public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
            {
                throw new TransportException('Connection refused');
            }

            public function __toString(): string
            {
                return 'failing';
            }
        });

        config([
            'mail.default' => 'failing',
            'mail.mailers.failing' => ['transport' => 'failing'],
        ]);
    }

    public function test_failed_delivery_shows_a_clear_message_instead_of_a_server_error(): void
    {
        $user = User::factory()->create();

        $this->post('/recuperar-cuenta', ['email' => $user->email])
            ->assertRedirect()
            ->assertSessionHasErrors(['email' => __('auth.recovery_send_failed')])
            ->assertSessionMissing('recovery.email');
    }

    public function test_failed_delivery_invalidates_the_otp_that_was_not_delivered(): void
    {
        $user = User::factory()->create();

        $this->assertFalse(app(AccountRecoveryService::class)->sendOtp($user));

        $this->assertNotNull(AccountRecoveryOtp::where('user_id', $user->id)->first()->consumed_at);
    }

    public function test_failed_resend_shows_a_clear_message(): void
    {
        $user = User::factory()->create();

        $this->withSession(['recovery.email' => $user->email])
            ->post('/recuperar-cuenta/reenviar')
            ->assertSessionHasErrors(['otp' => __('auth.recovery_send_failed')]);
    }

    public function test_failure_log_contains_only_technical_fields(): void
    {
        $user = User::factory()->create();

        Log::shouldReceive('error')
            ->once()
            ->withArgs(fn (string $message, array $context) => array_keys($context) === ['user_id', 'exception', 'message']);

        app(AccountRecoveryService::class)->sendOtp($user);
    }

    public function test_unknown_emails_still_get_the_same_response(): void
    {
        $this->post('/recuperar-cuenta', ['email' => 'nadie@example.com'])
            ->assertRedirect('/recuperar-cuenta/verificar')
            ->assertSessionHas('status', __('auth.recovery_sent'));
    }
}
