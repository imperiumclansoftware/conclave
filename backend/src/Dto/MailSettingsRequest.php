<?php

namespace App\Dto;

use App\Mail\MailServer;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Mail service, as edited in the administration. Also used to test values before saving them.
 */
final readonly class MailSettingsRequest
{
    public function __construct(
        #[Assert\Choice(choices: MailServer::MODES, message: 'Mode d\'envoi inconnu.')]
        public string $mode = MailServer::SMTP,
        #[Assert\Length(max: 255)]
        public ?string $host = null,
        #[Assert\Range(min: 1, max: 65535, notInRangeMessage: 'Le port est compris entre {{ min }} et {{ max }}.')]
        public ?int $port = null,
        #[Assert\Choice(choices: MailServer::SECURITIES, message: 'Chiffrement inconnu.')]
        public string $security = MailServer::STARTTLS,
        #[Assert\Length(max: 255)]
        public ?string $username = null,
        /** null keeps the saved password, "" removes it */
        #[Assert\Length(max: 255)]
        public ?string $password = null,
        public bool $verifyPeer = true,
        /** Custom DSN; null keeps the saved one */
        #[Assert\Length(max: 500)]
        #[Assert\Regex(pattern: '#^([a-z0-9+.-]+://.*)?$#i', message: 'Le DSN commence par smtp://, ses+smtp://, brevo+api://…')]
        public ?string $dsn = null,
        #[Assert\Email(normalizer: 'trim')]
        #[Assert\Length(max: 180)]
        public ?string $from = null,
        #[Assert\Length(max: 100)]
        public ?string $fromName = null,
        /** Test only: where to send the test e-mail (the current leader by default) */
        #[Assert\Email(normalizer: 'trim')]
        #[Assert\Length(max: 180)]
        public ?string $recipient = null,
    ) {
    }

    #[Assert\Callback]
    public function validateServer(ExecutionContextInterface $context): void
    {
        if (MailServer::SMTP !== $this->mode) {
            return;
        }
        if (null === $this->host || '' === trim($this->host)) {
            $context->buildViolation('Indiquez l\'adresse du serveur SMTP.')->atPath('host')->addViolation();
        } elseif (!preg_match('/^[a-z0-9]([a-z0-9.-]*[a-z0-9])?$/i', trim($this->host))) {
            $context->buildViolation('Indiquez seulement le nom du serveur, par exemple smtp.exemple.fr.')->atPath('host')->addViolation();
        }
    }
}
