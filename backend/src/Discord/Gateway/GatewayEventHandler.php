<?php

namespace App\Discord\Gateway;

use App\Discord\VideoCallService;
use App\Entity\User;
use App\Presence\PresenceTracker;
use App\Repository\ClanRepository;
use App\Repository\UserRepository;
use App\Repository\VideoCallRepository;

/**
 * Applies the Discord gateway events to the app: member presences and call channel occupancy.
 * Stateful (voice states are tracked in memory), used by the long-running gateway worker.
 */
final class GatewayEventHandler
{
    /** @var array<string, array<string, string>> guild id => (Discord user id => voice channel id) */
    private array $voiceStates = [];

    public function __construct(
        private readonly ClanRepository $clans,
        private readonly UserRepository $users,
        private readonly VideoCallRepository $calls,
        private readonly PresenceTracker $presence,
        private readonly VideoCallService $videoCalls,
    ) {
    }

    /** @param array<string, mixed> $data */
    public function handle(string $type, array $data): void
    {
        match ($type) {
            'GUILD_CREATE' => $this->guildCreate($data),
            'PRESENCE_UPDATE' => $this->presenceUpdate($data),
            'VOICE_STATE_UPDATE' => $this->voiceStateUpdate($data),
            default => null,
        };
    }

    /**
     * Initial state of a server: presences of online members, voice states.
     *
     * @param array<string, mixed> $guild
     */
    private function guildCreate(array $guild): void
    {
        $guildId = $guild['id'];
        $online = [];
        foreach ($guild['presences'] ?? [] as $presence) {
            $online[$presence['user']['id']] = $presence;
        }

        // Members absent from the list are offline
        foreach ($this->membersOf($guildId) as $user) {
            $presence = $online[$user->getDiscordId()] ?? null;
            $this->presence->discordPresence(
                $user,
                $presence['status'] ?? 'offline',
                $presence ? ActivityFormatter::format($presence['activities'] ?? []) : null,
            );
        }

        $this->voiceStates[$guildId] = [];
        foreach ($guild['voice_states'] ?? [] as $state) {
            if (null !== ($state['channel_id'] ?? null)) {
                $this->voiceStates[$guildId][$state['user_id']] = $state['channel_id'];
            }
        }
        foreach ($this->clans->findBy(['discordGuildId' => $guildId]) as $clan) {
            foreach ($this->calls->findActiveInClan($clan) as $call) {
                $this->videoCalls->updateConnected($call, $this->connectedTo($guildId, $call->getDiscordChannelId()));
            }
        }
    }

    /** @param array<string, mixed> $presence */
    private function presenceUpdate(array $presence): void
    {
        $user = $this->member($presence['guild_id'] ?? '', $presence['user']['id'] ?? '');
        if (null !== $user) {
            $this->presence->discordPresence($user, $presence['status'] ?? 'offline', ActivityFormatter::format($presence['activities'] ?? []));
        }
    }

    /** @param array<string, mixed> $state */
    private function voiceStateUpdate(array $state): void
    {
        $guildId = $state['guild_id'] ?? null;
        $userId = $state['user_id'] ?? null;
        if (null === $guildId || null === $userId) {
            return;
        }

        $previous = $this->voiceStates[$guildId][$userId] ?? null;
        $current = $state['channel_id'] ?? null;
        if (null === $current) {
            unset($this->voiceStates[$guildId][$userId]);
        } else {
            $this->voiceStates[$guildId][$userId] = $current;
        }

        foreach (array_unique(array_filter([$previous, $current])) as $channelId) {
            if ($call = $this->calls->findActiveByChannel($channelId)) {
                $this->videoCalls->updateConnected($call, $this->connectedTo($guildId, $channelId));
            }
        }
    }

    /** @return list<string> */
    private function connectedTo(string $guildId, string $channelId): array
    {
        // Numeric Discord ids used as array keys are turned into integers by PHP: cast them back
        return array_map(strval(...), array_keys(array_filter($this->voiceStates[$guildId] ?? [], static fn (string $c): bool => $c === $channelId)));
    }

    /** @return list<User> app users linked to Discord, in the clans using this server */
    private function membersOf(string $guildId): array
    {
        return $this->users->createQueryBuilder('u')
            ->innerJoin('u.clan', 'c')
            ->where('c.discordGuildId = :guild')
            ->andWhere('u.discordId IS NOT NULL')
            ->setParameter('guild', $guildId)
            ->getQuery()
            ->getResult();
    }

    private function member(string $guildId, string $discordId): ?User
    {
        $user = $this->users->findOneBy(['discordId' => $discordId]);

        return $user?->getClan()->getDiscordGuildId() === $guildId ? $user : null;
    }
}
