<?php

namespace App\Discord;

use App\Chat\RealtimeNotifier;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Completes the OAuth flows: links a member's Discord account, connects the clan's server.
 */
final readonly class DiscordLinker
{
    public function __construct(
        private DiscordApi $api,
        private UserRepository $users,
        private EntityManagerInterface $em,
        private RealtimeNotifier $notifier,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @throws DiscordApiException
     * @throws \DomainException    when the Discord account is linked to another member
     */
    public function complete(User $user, string $purpose, string $code): void
    {
        $token = $this->api->exchangeCode($code);
        $account = $this->api->currentUser($token['access_token']);

        $owner = $this->users->findOneBy(['discordId' => $account['id']]);
        if (null !== $owner && $owner->getId() !== $user->getId()) {
            throw new \DomainException('Ce compte Discord est déjà lié à un autre membre.');
        }
        $user->linkDiscord($account['id'], $account['global_name'] ?? $account['username'], $account['avatar'] ?? null);

        $clan = $user->getClan();
        if (DiscordOAuth::INSTALL === $purpose) {
            if (!$user->isLeader() || !isset($token['guild']['id'])) {
                throw new \DomainException('Le bot n\'a pas été ajouté à un serveur.');
            }
            $clan->connectDiscordGuild($token['guild']['id'], $token['guild']['name']);
        } elseif (null !== $clan->getDiscordGuildId()) {
            // Join the clan's server right away (best effort: the member can still join later)
            try {
                $this->api->addGuildMember($clan->getDiscordGuildId(), $account['id'], $token['access_token']);
            } catch (DiscordApiException $e) {
                $this->logger->warning('Unable to add {user} to the Discord server: {error}', ['user' => $user->getUsername(), 'error' => $e->getMessage()]);
            }
        }

        $this->em->flush();
        $this->notifier->presenceChanged($user);
    }

    public function unlink(User $user): void
    {
        $user->unlinkDiscord();
        $this->em->flush();
        $this->notifier->presenceChanged($user);
    }

    public function disconnectGuild(User $leader): void
    {
        $clan = $leader->getClan();
        if (null === $guildId = $clan->getDiscordGuildId()) {
            return;
        }

        try {
            $this->api->leaveGuild($guildId);
        } catch (DiscordApiException $e) {
            $this->logger->warning('Unable to remove the bot from the server: {error}', ['error' => $e->getMessage()]);
        }
        $clan->disconnectDiscordGuild();
        $this->em->flush();
    }
}
