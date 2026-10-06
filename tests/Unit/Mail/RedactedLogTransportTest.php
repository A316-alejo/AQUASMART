<?php

namespace Tests\Unit\Mail;

use App\Mail\Transport\RedactedLogTransport;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Symfony\Component\Mime\Email;

class RedactedLogTransportTest extends TestCase
{
    public function test_it_logs_only_safe_headers_and_never_the_body(): void
    {
        $logger = new class extends AbstractLogger
        {
            /** @var list<string> */
            public array $messages = [];

            public function log($level, $message, array $context = []): void
            {
                $this->messages[] = (string) $message;
            }
        };

        $transport = new RedactedLogTransport($logger);

        $email = (new Email)
            ->from('no-reply@aquasmart.test')
            ->to('usuario@example.com')
            ->subject('Codigo de recuperacion de cuenta - AquaSmart')
            ->text("Tu codigo de verificacion es: 913482\nNo lo compartas con nadie.");

        $transport->send($email);

        $this->assertCount(1, $logger->messages);
        $logged = $logger->messages[0];

        $this->assertStringNotContainsString('913482', $logged);
        $this->assertStringNotContainsString('No lo compartas', $logged);
        $this->assertStringContainsString('usuario@example.com', $logged);
        $this->assertStringContainsString('Codigo de recuperacion de cuenta', $logged);
    }

    public function test_it_identifies_itself_as_the_log_transport(): void
    {
        $logger = new class extends AbstractLogger
        {
            public function log($level, $message, array $context = []): void {}
        };

        $this->assertSame('log', (string) new RedactedLogTransport($logger));
    }
}
