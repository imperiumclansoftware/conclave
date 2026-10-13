<?php

namespace App\Mail;

use Symfony\Component\Mailer\Exception\InvalidArgumentException;
use Symfony\Component\Mailer\Transport\Dsn;

/**
 * Mail server described field by field for the administration, stored as a Mailer DSN
 * (so that MAILER_DSN in .env keeps working).
 */
final readonly class MailServer
{
    public const SMTP = 'smtp';
    public const SENDMAIL = 'sendmail';
    public const DISABLED = 'disabled';
    /** Any other DSN (third-party transports, unusual options), edited as is */
    public const CUSTOM = 'custom';
    public const MODES = [self::SMTP, self::SENDMAIL, self::DISABLED, self::CUSTOM];

    /** STARTTLS required (usually port 587) */
    public const STARTTLS = 'starttls';
    /** Implicit TLS (smtps, usually port 465) */
    public const TLS = 'tls';
    /** Unencrypted, for a local relay or a test server */
    public const PLAIN = 'none';
    public const SECURITIES = [self::STARTTLS, self::TLS, self::PLAIN];

    public function __construct(
        public string $mode,
        public ?string $host = null,
        public ?int $port = null,
        public string $security = self::STARTTLS,
        public ?string $username = null,
        #[\SensitiveParameter] public ?string $password = null,
        public bool $verifyPeer = true,
        #[\SensitiveParameter] public ?string $customDsn = null,
    ) {
    }

    public static function fromDsn(#[\SensitiveParameter] ?string $dsn): self
    {
        if (null === $dsn) {
            return new self(self::DISABLED);
        }

        try {
            $parsed = Dsn::fromString($dsn);
        } catch (InvalidArgumentException) {
            return new self(self::CUSTOM, customDsn: $dsn);
        }

        return match ($parsed->getScheme()) {
            'null' => new self(self::DISABLED),
            'sendmail' => new self(self::SENDMAIL),
            'smtp', 'smtps' => new self(
                self::SMTP,
                host: $parsed->getHost(),
                port: $parsed->getPort(),
                security: match (true) {
                    'smtps' === $parsed->getScheme() => self::TLS,
                    !filter_var($parsed->getOption('auto_tls', true), \FILTER_VALIDATE_BOOL) => self::PLAIN,
                    default => self::STARTTLS,
                },
                username: $parsed->getUser(),
                password: $parsed->getPassword(),
                verifyPeer: filter_var($parsed->getOption('verify_peer', true), \FILTER_VALIDATE_BOOL),
            ),
            default => new self(self::CUSTOM, customDsn: $dsn),
        };
    }

    /** Null when sending is disabled. */
    public function toDsn(): ?string
    {
        return match ($this->mode) {
            self::SENDMAIL => 'sendmail://default',
            self::CUSTOM => $this->customDsn,
            self::SMTP => $this->smtpDsn(),
            default => null,
        };
    }

    private function smtpDsn(): string
    {
        $credentials = '';
        if (null !== $this->username) {
            // Encoded here, so that passwords with "@", ":" or "/" need no care from the leader
            $credentials = rawurlencode($this->username).(null !== $this->password ? ':'.rawurlencode($this->password) : '').'@';
        }
        $options = array_filter([
            'require_tls' => self::STARTTLS === $this->security ? 'true' : null,
            'auto_tls' => self::PLAIN === $this->security ? 'false' : null,
            'verify_peer' => $this->verifyPeer ? null : 'false',
        ]);

        return \sprintf(
            '%s://%s%s%s%s',
            self::TLS === $this->security ? 'smtps' : 'smtp',
            $credentials,
            $this->host,
            null !== $this->port ? ':'.$this->port : '',
            $options ? '?'.http_build_query($options) : '',
        );
    }
}
