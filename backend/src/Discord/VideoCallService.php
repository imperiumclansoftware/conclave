<?php

namespace App\Discord;

use App\Chat\RealtimeNotifier;
use App\Entity\Clan;
use App\Entity\User;
use App\Entity\VideoCall;
use App\Repository\VideoCallRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Video calls between chosen clan members, held in temporary private Discord voice channels
 * (only the participants can see and join them; video and screen sharing allowed).
 */
final readonly class VideoCallService
{
    /** A call nobody joined is cleaned up after this delay. */
    public const UNUSED_CALL_TTL = '-15 minutes';

    public function __construct(
        private DiscordApi $api,
        private VideoCallRepository $calls,
        private EntityManagerInterface $em,
        private RealtimeNotifier $notifier,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param list<User> $invitees
     *
     * @throws \DomainException    when the call cannot be created (with a message for the user)
     * @throws DiscordApiException
     */
    public function create(User $creator, array $invitees, ?string $title = null): VideoCall
    {
        $clan = $creator->getClan();
        $guildId = $clan->getDiscordGuildId() ?? throw new \DomainException('Le serveur Discord du clan n\'est pas connecté.');

        $participants = [$creator->getId() => $creator];
        foreach ($invitees as $user) {
            if ($user->getClan()->getId() !== $clan->getId()) {
                throw new \DomainException('Seuls les membres du clan peuvent être invités.');
            }
            $participants[$user->getId()] = $user;
        }
        if (\count($participants) < 2) {
            throw new \DomainException('Choisissez au moins un membre à inviter.');
        }

        $missing = array_filter($participants, static fn (User $u): bool => null === $u->getDiscordId());
        if ($missing) {
            throw new \DomainException(\sprintf('Compte Discord non lié : %s.', self::names($missing)));
        }
        $notOnServer = array_filter($participants, fn (User $u): bool => !$this->api->isGuildMember($guildId, $u->getDiscordId()));
        if ($notOnServer) {
            throw new \DomainException(\sprintf('Pas encore sur le serveur Discord du clan : %s.', self::names($notOnServer)));
        }

        $title = trim((string) $title) ?: 'Visio de '.$creator->getDisplayName();
        $channel = $this->api->createChannel($guildId, [
            'name' => mb_substr('🎥 '.$title, 0, 100),
            'type' => DiscordApi::CHANNEL_VOICE,
            'parent_id' => $this->callCategory($clan),
            'permission_overwrites' => $this->overwrites($guildId, $participants),
        ]);

        $call = new VideoCall($clan, $creator, mb_substr($title, 0, 100), $channel['id'], array_values($participants));
        $this->em->persist($call);
        $this->em->flush();

        $this->notifier->callUpdated($call);
        foreach ($participants as $user) {
            if ($user !== $creator) {
                $this->invite($user, $creator, $call);
            }
        }

        return $call;
    }

    public function end(VideoCall $call): void
    {
        if ($call->isEnded()) {
            return;
        }

        try {
            $this->api->deleteChannel($call->getDiscordChannelId());
        } catch (DiscordApiException $e) {
            $this->logger->warning('Unable to delete the call channel {id}: {error}', ['id' => $call->getDiscordChannelId(), 'error' => $e->getMessage()]);
        }
        $call->end();
        $this->em->flush();
        $this->notifier->callEnded($call);
    }

    /**
     * Who is in the call's voice channel (reported by the gateway). The call ends when the
     * last participant leaves.
     *
     * @param list<string> $discordIds
     */
    public function updateConnected(VideoCall $call, array $discordIds): void
    {
        $wasStarted = $call->isStarted();
        $before = $call->getConnectedDiscordIds();
        $call->setConnectedDiscordIds($discordIds);

        if ($wasStarted && [] === $call->getConnectedDiscordIds()) {
            $this->end($call);

            return;
        }

        if ($before !== $call->getConnectedDiscordIds()) {
            $this->em->flush();
            $this->notifier->callUpdated($call);
        }
    }

    /** Ends the calls nobody joined. */
    public function sweepUnused(): int
    {
        $limit = new \DateTimeImmutable(self::UNUSED_CALL_TTL);
        $ended = 0;
        foreach ($this->calls->findAllActive() as $call) {
            if (!$call->isStarted() && $call->getCreatedAt() < $limit) {
                $this->end($call);
                ++$ended;
            }
        }

        return $ended;
    }

    /** Hidden category grouping the clan's call channels, created on first use. */
    private function callCategory(Clan $clan): string
    {
        $categoryId = $clan->getDiscordCallCategoryId();
        if (null !== $categoryId && null !== $this->api->channel($categoryId)) {
            return $categoryId;
        }

        $guildId = $clan->getDiscordGuildId();
        $category = $this->api->createChannel($guildId, [
            'name' => 'Visios du clan',
            'type' => DiscordApi::CHANNEL_CATEGORY,
            'permission_overwrites' => [
                ['id' => $guildId, 'type' => DiscordApi::OVERWRITE_ROLE, 'deny' => (string) DiscordPermission::VIEW_CHANNEL],
                ['id' => $this->api->botUser()['id'], 'type' => DiscordApi::OVERWRITE_MEMBER, 'allow' => (string) (DiscordPermission::VIEW_CHANNEL | DiscordPermission::MANAGE_CHANNELS)],
            ],
        ]);
        $clan->setDiscordCallCategoryId($category['id']);

        return $category['id'];
    }

    /**
     * Private channel: hidden from @everyone (whose role id is the server id), open to the bot
     * and to the participants.
     *
     * @param array<User> $participants
     *
     * @return list<array{id: ?string, type: int, deny?: string, allow?: string}>
     */
    private function overwrites(string $guildId, array $participants): array
    {
        $overwrites = [
            ['id' => $guildId, 'type' => DiscordApi::OVERWRITE_ROLE, 'deny' => (string) (DiscordPermission::VIEW_CHANNEL | DiscordPermission::CONNECT)],
            ['id' => $this->api->botUser()['id'], 'type' => DiscordApi::OVERWRITE_MEMBER, 'allow' => (string) (DiscordPermission::VIEW_CHANNEL | DiscordPermission::CONNECT | DiscordPermission::MANAGE_CHANNELS)],
        ];
        foreach ($participants as $user) {
            $overwrites[] = ['id' => $user->getDiscordId(), 'type' => DiscordApi::OVERWRITE_MEMBER, 'allow' => (string) DiscordPermission::CALL_PARTICIPANT];
        }

        return $overwrites;
    }

    private function invite(User $user, User $creator, VideoCall $call): void
    {
        try {
            $this->api->sendDirectMessage(
                $user->getDiscordId(),
                \sprintf('🎥 **%s** vous invite à une visio du clan : **%s**', $creator->getDisplayName(), $call->getTitle()),
                'Rejoindre la visio',
                $call->getJoinUrl(),
            );
        } catch (DiscordApiException $e) {
            // DMs may be closed: the member is also notified in the app
            $this->logger->info('Unable to send a call invitation to {user}: {error}', ['user' => $user->getUsername(), 'error' => $e->getMessage()]);
        }
    }

    /** @param array<User> $users */
    private static function names(array $users): string
    {
        return implode(', ', array_map(static fn (User $u): string => $u->getDisplayName(), $users));
    }
}
