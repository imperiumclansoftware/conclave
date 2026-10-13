<?php

namespace App\Mail;

use App\Entity\User;
use App\Repository\ClanRepository;
use App\Settings\AppSettings;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;

/**
 * E-mails sent by the app. Sending is best effort: a missing or failing mail service never
 * blocks the action that triggered the e-mail.
 */
final readonly class AppMailer
{
    public function __construct(
        private MailerInterface $mailer,
        private AppSettings $settings,
        private ClanRepository $clans,
        private LoggerInterface $logger,
    ) {
    }

    public function isConfigured(): bool
    {
        return null !== $this->settings->get(AppSettings::MAILER_DSN) && null !== $this->settings->get(AppSettings::MAILER_FROM);
    }

    /**
     * Tells the leaders that someone asks to join the clan.
     *
     * @param iterable<User> $leaders
     */
    public function signupRequested(User $applicant, iterable $leaders): void
    {
        foreach ($leaders as $leader) {
            if (null !== $leader->getEmail()) {
                $this->send($leader, 'Nouvelle demande d\'inscription : '.$applicant->getDisplayName(), 'signup_requested', [
                    'applicant' => $applicant,
                    'link' => $this->link('admin'),
                ]);
            }
        }
    }

    public function accountApproved(User $user): void
    {
        $this->send($user, 'Bienvenue dans le clan !', 'account_approved', ['link' => $this->link('login')]);
    }

    public function accountRejected(User $user): void
    {
        $this->send($user, 'Votre demande d\'inscription', 'account_rejected');
    }

    public function passwordReset(User $user, string $token, int $validMinutes): void
    {
        $this->send($user, 'Réinitialisation de votre mot de passe', 'password_reset', [
            'link' => $this->link('reset-password?token='.urlencode($token)),
            'validMinutes' => $validMinutes,
        ]);
    }

    /**
     * Test e-mail from the administration, through a server that may not be saved yet.
     *
     * @throws TransportExceptionInterface with the reason of the failure
     */
    public function test(TransportInterface $transport, User $leader, string $recipient, string $from, ?string $fromName): void
    {
        $email = $this->email($leader, 'E-mail de test', 'test', [], new Address($from, $fromName ?? $this->clanName()))
            ->to(new Address($recipient));
        $transport->send($email);
    }

    /** @param array<string, mixed> $context */
    private function send(User $to, string $subject, string $template, array $context = []): void
    {
        if (null === $to->getEmail() || !$this->isConfigured()) {
            return;
        }

        try {
            $this->mailer->send($this->email($to, $subject, $template, $context));
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Unable to send the "{template}" e-mail to {user}: {error}', [
                'template' => $template,
                'user' => $to->getUsername(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** @param array<string, mixed> $context */
    private function email(User $to, string $subject, string $template, array $context, ?Address $from = null): TemplatedEmail
    {
        $clanName = $this->clanName();
        $from ??= new Address((string) $this->settings->get(AppSettings::MAILER_FROM), $this->settings->get(AppSettings::MAILER_FROM_NAME) ?? $clanName);

        return (new TemplatedEmail())
            ->from($from)
            ->to(new Address((string) $to->getEmail(), $to->getDisplayName()))
            ->subject(\sprintf('[%s] %s', $clanName, $subject))
            ->htmlTemplate("emails/$template.html.twig")
            ->context($context + ['user' => $to, 'clanName' => $clanName]);
    }

    private function clanName(): string
    {
        return $this->clans->findMain()?->getName() ?? 'Le clan';
    }

    /** Link to a page of the app, when its public URL is known. */
    private function link(string $path): ?string
    {
        $base = $this->settings->get(AppSettings::APP_URL);

        return null !== $base ? rtrim($base, '/').'/'.$path : null;
    }
}
