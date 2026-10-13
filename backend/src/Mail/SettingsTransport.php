<?php

namespace App\Mail;

use App\Settings\AppSettings;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\RawMessage;

/**
 * Mailer transport whose DSN (smtp://…) is read from the settings at sending time, so that it
 * can be changed in the administration without redeploying.
 */
final class SettingsTransport implements TransportInterface
{
    /** @var array<string, TransportInterface> transports by DSN */
    private array $transports = [];

    public function __construct(
        private readonly AppSettings $settings,
        private readonly ?EventDispatcherInterface $dispatcher = null,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        $dsn = $this->settings->get(AppSettings::MAILER_DSN)
            ?? throw new TransportException('Le service de mail n\'est pas configuré.');

        // The dispatcher renders the Twig templates (BodyRenderer) and logs the messages (profiler, tests)
        return ($this->transports[$dsn] ??= Transport::fromDsn($dsn, $this->dispatcher, logger: $this->logger))->send($message, $envelope);
    }

    public function __toString(): string
    {
        return 'settings://default';
    }
}
