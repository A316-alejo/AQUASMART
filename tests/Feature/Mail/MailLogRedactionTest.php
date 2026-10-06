<?php

namespace Tests\Feature\Mail;

use App\Mail\Transport\RedactedLogTransport;
use App\Models\User;
use App\Services\AccountRecoveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Password;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

class MailLogRedactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_log_mail_driver_resolves_to_the_redacted_transport(): void
    {
        $mailer = app('mail.manager')->mailer('log');

        $this->assertInstanceOf(RedactedLogTransport::class, $mailer->getSymfonyTransport());
    }

    public function test_sending_the_recovery_otp_through_the_log_driver_never_logs_the_code(): void
    {
        config(['mail.default' => 'log']);
        $this->app->instance(LoggerInterface::class, $logger = $this->fakeLogger());

        $user = User::factory()->create();
        app(AccountRecoveryService::class)->sendOtp($user);

        $this->assertNotEmpty($logger->messages);
        foreach ($logger->messages as $message) {
            $this->assertDoesNotMatchRegularExpression('/\b[0-9]{6}\b/', $message);
        }
    }

    public function test_sending_the_native_password_reset_link_through_the_log_driver_never_logs_the_token(): void
    {
        config(['mail.default' => 'log']);
        $this->app->instance(LoggerInterface::class, $logger = $this->fakeLogger());

        $user = User::factory()->create();
        Password::sendResetLink(['email' => $user->email]);

        $this->assertNotEmpty($logger->messages);
        foreach ($logger->messages as $message) {
            $this->assertStringNotContainsString('restablecer-contrasena', $message);
            $this->assertStringNotContainsString('token=', $message);
        }
    }

    /**
     * @return AbstractLogger&object{messages: list<string>}
     */
    private function fakeLogger(): AbstractLogger
    {
        return new class extends AbstractLogger
        {
            /** @var list<string> */
            public array $messages = [];

            public function log($level, $message, array $context = []): void
            {
                $this->messages[] = (string) $message;
            }
        };
    }
}
