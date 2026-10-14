<?php

namespace App\Enum;

/**
 * Kinds of events, each belonging to a branch, with the fields it needs besides the title,
 * the dates and the description. Adding a type: a case, its branch, its label and its fields.
 */
enum EventType: string
{
    case GameSession = 'game_session';
    case Tournament = 'tournament';
    case WatchParty = 'watch_party';
    case CineClub = 'cine_club';
    case Meeting = 'meeting';
    case GameJam = 'game_jam';
    case Stream = 'stream';

    public function branch(): BranchCrest
    {
        return match ($this) {
            self::GameSession, self::Tournament => BranchCrest::Players,
            self::WatchParty, self::CineClub => BranchCrest::Cinephiles,
            self::Meeting, self::GameJam => BranchCrest::Developers,
            self::Stream => BranchCrest::Communicants,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::GameSession => 'Session de jeu',
            self::Tournament => 'Tournoi',
            self::WatchParty => 'Watch party',
            self::CineClub => 'Ciné-club',
            self::Meeting => 'Réunion',
            self::GameJam => 'Game jam',
            self::Stream => 'Stream',
        };
    }

    /**
     * Specific fields: name => required.
     *
     * @return array<'game'|'gamePlatform'|'film'|'filmPlatform'|'streamPlatform'|'link'|'theme', bool>
     */
    public function fields(): array
    {
        return match ($this) {
            self::GameSession, self::Tournament => ['game' => true, 'gamePlatform' => true],
            self::WatchParty => ['film' => true, 'filmPlatform' => true],
            self::CineClub => ['film' => true],
            self::Meeting => ['link' => false],
            self::GameJam => ['theme' => false, 'link' => false],
            self::Stream => ['streamPlatform' => true, 'link' => false],
        };
    }

    /** @return list<self> types of a branch */
    public static function forBranch(BranchCrest $crest): array
    {
        return array_values(array_filter(self::cases(), static fn (self $type): bool => $type->branch() === $crest));
    }
}
