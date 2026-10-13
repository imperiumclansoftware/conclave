<?php

namespace App\Mail;

use App\Entity\User;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Transport\Smtp\SmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;

/**
 * Checks a mail server step by step (connection, authentication, sending) and explains the
 * usual failures, so that a leader can fix the settings without reading SMTP logs.
 */
final readonly class MailServerTester
{
    /** Seconds before giving up on an unreachable server, instead of PHP's default 60 */
    private const TIMEOUT = 10;

    public function __construct(
        private AppMailer $mailer,
        private EventDispatcherInterface $dispatcher,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @return list<array{label: string, ok: bool, detail: ?string}>
     */
    public function test(MailServer $server, ?string $from, ?string $fromName, string $recipient, User $leader): array
    {
        $dsn = $server->toDsn();
        if (null === $dsn) {
            return [self::check('Service', false, 'L\'envoi d\'e-mails est désactivé.')];
        }
        if (null === $from) {
            return [self::check('Expéditeur', false, 'Indiquez l\'adresse d\'expédition.')];
        }

        try {
            // The dispatcher renders the Twig template and logs the message (profiler, tests)
            $transport = Transport::fromDsn($dsn, $this->dispatcher, logger: $this->logger);
        } catch (\Throwable $e) {
            return [self::check('Configuration', false, $e->getMessage())];
        }

        $checks = [];
        if ($transport instanceof SmtpTransport) {
            $stream = $transport->getStream();
            $target = 'au serveur';
            if ($stream instanceof SocketStream) {
                $stream->setTimeout(self::TIMEOUT);
                $target = \sprintf('à %s:%d', $stream->getHost(), $stream->getPort());
            }
            try {
                // Connects, negotiates TLS and authenticates, without sending anything
                $transport->start();
                $checks[] = self::check('Connexion '.$target, true, $this->encryption($server));
                if (null !== $server->username) {
                    $checks[] = self::check('Authentification', true, 'Accepté pour '.$server->username.'.');
                }
            } catch (TransportExceptionInterface $e) {
                if (self::isAuthenticationFailure($e)) {
                    $checks[] = self::check('Connexion '.$target, true, $this->encryption($server));
                    $checks[] = self::check('Authentification', false, 'Identifiant ou mot de passe refusé. Gmail, Outlook et Yahoo exigent un « mot de passe d\'application » (validation en deux étapes activée).');
                } else {
                    $checks[] = self::check('Connexion '.$target, false, self::explainConnection($e, $server));
                }

                return $checks;
            }
        }

        try {
            $this->mailer->test($transport, $leader, $recipient, $from, $fromName);
            $checks[] = self::check('Envoi', true, 'E-mail envoyé à '.$recipient.'. Pensez à regarder dans les indésirables.');
        } catch (TransportExceptionInterface $e) {
            $checks[] = self::check('Envoi', false, self::explainSending($e));
        } finally {
            if ($transport instanceof SmtpTransport) {
                try {
                    $transport->stop();
                } catch (TransportExceptionInterface) {
                    // Already disconnected
                }
            }
        }

        return $checks;
    }

    private function encryption(MailServer $server): string
    {
        return match ($server->security) {
            MailServer::TLS => 'Chiffré (SSL/TLS).',
            MailServer::STARTTLS => 'Chiffré (STARTTLS).',
            default => 'Non chiffré : à réserver à un serveur local ou de test.',
        };
    }

    private static function isAuthenticationFailure(TransportExceptionInterface $e): bool
    {
        return 535 === $e->getCode() || 534 === $e->getCode() || str_contains($e->getMessage(), 'Failed to authenticate');
    }

    private static function explainConnection(TransportExceptionInterface $e, MailServer $server): string
    {
        $message = $e->getMessage();
        $hint = match (true) {
            str_contains($message, 'TLS required') || str_contains($message, 'STARTTLS') => 'Le serveur ne propose pas STARTTLS : essayez SSL/TLS sur le port 465.',
            (bool) preg_match('/certificate|SSL operation failed|crypto/i', $message) => MailServer::TLS === $server->security
                ? 'Échec de la négociation TLS : vérifiez le port (465 pour SSL/TLS, 587 pour STARTTLS) ou le certificat du serveur.'
                : 'Certificat refusé. Pour un serveur auto-hébergé, cochez « Accepter un certificat non vérifié ».',
            (bool) preg_match('/getaddrinfo|name or service not known|php_network_getaddresses/i', $message) => 'Serveur introuvable : vérifiez son adresse.',
            (bool) preg_match('/timed out|timeout/i', $message) => 'Le serveur ne répond pas : vérifiez le port et que l\'hébergeur n\'en bloque pas la sortie (souvent le 25).',
            (bool) preg_match('/refused/i', $message) => 'Connexion refusée : vérifiez le port.',
            (bool) preg_match('/Expected response code "220"/', $message) => 'Réponse inattendue : le chiffrement ne correspond sans doute pas au port (465 = SSL/TLS, 587 = STARTTLS).',
            default => null,
        };

        return self::withCause($hint, $message);
    }

    private static function explainSending(TransportExceptionInterface $e): string
    {
        $message = $e->getMessage();
        $hint = match (true) {
            (bool) preg_match('/\b55[0-4]\b.*(sender|from|not owned|not allowed|relay)|"MAIL FROM"/is', $message) => 'Adresse d\'expédition refusée : utilisez une adresse du compte SMTP ou un domaine autorisé chez le fournisseur.',
            (bool) preg_match('/"RCPT TO"/', $message) => 'Destinataire refusé par le serveur.',
            default => null,
        };

        return self::withCause($hint, $message);
    }

    private static function withCause(?string $hint, string $message): string
    {
        $cause = mb_strimwidth(trim(preg_replace('/\s+/', ' ', $message)), 0, 200, '…');

        return null !== $hint ? \sprintf('%s (%s)', $hint, $cause) : $cause;
    }

    /** @return array{label: string, ok: bool, detail: ?string} */
    private static function check(string $label, bool $ok, ?string $detail): array
    {
        return ['label' => $label, 'ok' => $ok, 'detail' => $detail];
    }
}
