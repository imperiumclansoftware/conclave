<?php

namespace App\Mail;

use App\Settings\AppSettings;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportFactoryInterface;
use Symfony\Component\Mailer\Transport\TransportInterface;

/**
 * Handles the "settings://default" DSN of config/packages/mailer.yaml.
 */
#[AutoconfigureTag('mailer.transport_factory')]
final readonly class SettingsTransportFactory implements TransportFactoryInterface
{
    public function __construct(
        private AppSettings $settings,
        private EventDispatcherInterface $dispatcher,
        private LoggerInterface $logger,
    ) {
    }

    public function create(Dsn $dsn): TransportInterface
    {
        if (!$this->supports($dsn)) {
            throw new UnsupportedSchemeException($dsn, 'settings', ['settings']);
        }

        return new SettingsTransport($this->settings, $this->dispatcher, $this->logger);
    }

    public function supports(Dsn $dsn): bool
    {
        return 'settings' === $dsn->getScheme();
    }
}
