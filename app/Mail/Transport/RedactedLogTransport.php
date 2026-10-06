<?php

namespace App\Mail\Transport;

use Psr\Log\LoggerInterface;
use Stringable;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\RawMessage;

/**
 * Sustituye al transporte "log" nativo de Laravel, que escribe el correo completo
 * (asunto y cuerpo) en el log de la aplicación. Aquí solo se registran encabezados
 * no sensibles (Para, Asunto, Fecha): el cuerpo nunca se escribe, así que un OTP,
 * un enlace de restablecimiento u otro secreto enviado por correo no puede terminar
 * en storage/logs. Solo sustituye el driver "log"; un transporte real (SMTP/API)
 * no pasa por aquí y sigue entregando el correo sin cambios.
 */
class RedactedLogTransport implements Stringable, TransportInterface
{
    /**
     * @var list<string>
     */
    private const SAFE_HEADERS = ['To', 'Subject', 'Date', 'Message-ID'];

    public function __construct(private readonly LoggerInterface $logger) {}

    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        [$headers] = explode("\r\n\r\n", $message->toString(), 2) + [''];

        $summary = collect(self::SAFE_HEADERS)
            ->map(function (string $name) use ($headers) {
                if (preg_match('/^'.preg_quote($name, '/').':\s*(.+)$/mi', $headers, $matches)) {
                    return $name.': '.trim($matches[1]);
                }

                return null;
            })
            ->filter()
            ->implode(' | ');

        $this->logger->debug('[correo omitido por seguridad, solo encabezados] '.$summary);

        return new SentMessage($message, $envelope ?? Envelope::create($message));
    }

    public function __toString(): string
    {
        return 'log';
    }
}
