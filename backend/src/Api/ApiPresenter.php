<?php

namespace App\Api;

use App\Catalog\CatalogGame;
use App\Entity\Branch;
use App\Entity\BranchRequest;
use App\Entity\Clan;
use App\Entity\Conversation;
use App\Entity\ConversationParticipant;
use App\Entity\Event;
use App\Entity\EventParticipant;
use App\Entity\Film;
use App\Entity\Game;
use App\Entity\Message;
use App\Entity\News;
use App\Entity\User;
use App\Entity\UserGame;
use App\Entity\VideoCall;
use App\Enum\FilmPlatform;
use App\Enum\GamePlatform;
use App\Enum\StreamPlatform;
use App\Films\FilmsAccess;
use App\Games\GamesAccess;
use App\News\NewsPermissions;
use App\Presence\AppStatus;
use App\Profile\Avatars;
use App\Repository\BranchRepository;

/**
 * Turns entities into the JSON structures exposed by the API.
 */
final class ApiPresenter
{
    public function __construct(
        private readonly BranchRepository $branches,
        private readonly NewsPermissions $newsPermissions,
        private readonly GamesAccess $gamesAccess,
        private readonly FilmsAccess $filmsAccess,
    ) {
    }

    /** @return array{id: ?int, username: string, displayName: string, role: string, avatarUrl: ?string} */
    public function user(User $user): array
    {
        return [
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'displayName' => $user->getDisplayName(),
            'role' => $user->getClanRole()->value,
            'avatarUrl' => self::avatarUrl($user),
        ];
    }

    /** @return array{id: ?int, username: string, displayName: string, role: string, avatarUrl: ?string, clan: array{id: ?int, name: string}, email: ?string, faction: ?string, canPublishNews: bool, canAccessGames: bool, canAccessFilms: bool} */
    public function me(User $user): array
    {
        return $this->user($user) + [
            'clan' => $this->clan($user->getClan()),
            'email' => $user->getEmail(),
            'faction' => $user->getFaction()?->value,
            'canPublishNews' => $this->newsPermissions->canPublish($user),
            'canAccessGames' => $this->gamesAccess->allowed($user),
            'canAccessFilms' => $this->filmsAccess->allowed($user),
        ];
    }

    /**
     * Public profile, as seen by clan members.
     *
     * @return array{id: ?int, username: string, displayName: string, role: string, avatarUrl: ?string, bio: ?string, gamerTags: object, discordUsername: ?string, memberSince: string, branches: list<array{id: ?int, name: string, crest: ?string}>}
     */
    public function profile(User $user): array
    {
        return $this->user($user) + [
            'bio' => $user->getBio(),
            'gamerTags' => (object) $user->getGamerTags(),
            'discordUsername' => $user->getDiscordUsername(),
            'memberSince' => $user->getCreatedAt()->format(\DATE_ATOM),
            'branches' => array_map(
                static fn (Branch $branch): array => ['id' => $branch->getId(), 'name' => $branch->getName(), 'crest' => $branch->getCrest()?->value],
                $this->branches->findForMember($user),
            ),
        ];
    }

    /** @return array{id: ?int, title: string, body: string, imageUrl: ?string, author: ?array{id: ?int, username: string, displayName: string, role: string, avatarUrl: ?string}, createdAt: string, updatedAt: ?string, canEdit: bool, canDelete: bool} */
    public function news(News $news, bool $canEdit, bool $canDelete): array
    {
        $author = $news->getAuthor();

        return [
            'id' => $news->getId(),
            'title' => $news->getTitle(),
            'body' => $news->getBody(),
            'imageUrl' => null !== $news->getImageFile() ? 'api/news-images/'.$news->getImageFile() : null,
            'author' => null !== $author && $author->isActive() ? $this->user($author) : null,
            'createdAt' => $news->getCreatedAt()->format(\DATE_ATOM),
            'updatedAt' => $news->getUpdatedAt()?->format(\DATE_ATOM),
            'canEdit' => $canEdit,
            'canDelete' => $canDelete,
        ];
    }

    /**
     * For the leaders' administration.
     *
     * @return array{id: ?int, branch: array{id: ?int, name: string, crest: ?string}, member: array{id: ?int, username: string, displayName: string, role: string, avatarUrl: ?string}, createdAt: string}
     */
    public function branchRequest(BranchRequest $request): array
    {
        $branch = $request->getBranch();

        return [
            'id' => $request->getId(),
            'branch' => ['id' => $branch->getId(), 'name' => $branch->getName(), 'crest' => $branch->getCrest()?->value],
            'member' => $this->user($request->getUser()),
            'createdAt' => $request->getCreatedAt()->format(\DATE_ATOM),
        ];
    }

    /** @return array{id: ?int, name: string, description: ?string, crest: ?string, publishesNews: bool, approvalRequired: bool, active: bool, pendingIds: list<?int>, memberIds: list<?int>} */
    public function branch(Branch $branch): array
    {
        return [
            'id' => $branch->getId(),
            'name' => $branch->getName(),
            'description' => $branch->getDescription(),
            'crest' => $branch->getCrest()?->value,
            'publishesNews' => $branch->publishesNews(),
            'approvalRequired' => $branch->isApprovalRequired(),
            'active' => $branch->isActive(),
            'pendingIds' => $branch->getRequests()->map(static fn (BranchRequest $r): ?int => $r->getUser()->getId())->getValues(),
            'memberIds' => $branch->getMembers()->map(static fn (User $user): ?int => $user->getId())->getValues(),
        ];
    }

    public static function avatarUrl(User $user): ?string
    {
        return Avatars::url($user);
    }

    /**
     * The member's avatar settings (their own profile only).
     *
     * @return array{source: string, chosen: ?string, available: object}
     */
    public function avatarSettings(User $user): array
    {
        return [
            'source' => Avatars::effectiveSource($user)->value,
            'chosen' => $user->getAvatarSource()?->value,
            'available' => (object) Avatars::available($user),
        ];
    }

    /** @return array{id: ?int, name: string} */
    public function clan(Clan $clan): array
    {
        return [
            'id' => $clan->getId(),
            'name' => $clan->getName(),
        ];
    }

    /** @return array{id: ?int, conversationId: ?int, senderId: ?int, content: string, createdAt: string} */
    public function message(Message $message): array
    {
        return [
            'id' => $message->getId(),
            'conversationId' => $message->getConversation()->getId(),
            'senderId' => $message->getSender()->getId(),
            'content' => $message->getContent(),
            'createdAt' => $message->getCreatedAt()->format(\DATE_ATOM),
        ];
    }

    /** @return array{id: ?int, participants: list<array{id: ?int, username: string, displayName: string, role: string, avatarUrl: ?string}>, lastMessage: ?array{id: ?int, conversationId: ?int, senderId: ?int, content: string, createdAt: string}, lastReadMessageId: ?int, unreadCount: int} */
    public function conversation(Conversation $conversation, User $viewer, int $unreadCount = 0): array
    {
        $lastMessage = $conversation->getLastMessage();

        return [
            'id' => $conversation->getId(),
            'participants' => $conversation->getParticipants()
                ->map(fn (ConversationParticipant $p): array => $this->user($p->getUser()))
                ->getValues(),
            'lastMessage' => $lastMessage ? $this->message($lastMessage) : null,
            'lastReadMessageId' => $conversation->getParticipant($viewer)?->getLastReadMessageId(),
            'unreadCount' => $unreadCount,
        ];
    }

    /**
     * Event, as seen by a member; $rights says what this member can do with it.
     *
     * @param array{participate: bool, edit: bool, delete: bool} $rights
     *
     * @return array<string, mixed>
     */
    public function event(Event $event, User $viewer, array $rights): array
    {
        $branch = $event->getBranch();
        $game = $event->getGame();
        $author = $event->getCreatedBy();
        $participants = $event->getParticipants()->map(fn (EventParticipant $p): array => $this->user($p->getUser()))->getValues();

        return [
            'id' => $event->getId(),
            'type' => $event->getType()->value,
            'typeLabel' => $event->getType()->label(),
            'branch' => ['id' => $branch->getId(), 'name' => $branch->getName(), 'crest' => $branch->getCrest()?->value],
            'title' => $event->getTitle(),
            'description' => $event->getDescription(),
            'startsAt' => $event->getStartsAt()->format(\DATE_ATOM),
            'endsAt' => $event->getEndsAt()?->format(\DATE_ATOM),
            'maxParticipants' => $event->getMaxParticipants(),
            'game' => null !== $game ? ['id' => $game->getId(), 'name' => $game->getName(), 'coverUrl' => $game->getCoverUrl(), 'source' => $game->getSource(), 'externalId' => $game->getExternalId()] : null,
            'gamePlatform' => self::option($event->getGamePlatform()),
            'film' => null !== $event->getFilm() ? $this->film($event->getFilm()) : null,
            'filmPlatform' => self::option($event->getFilmPlatform()),
            'streamPlatform' => self::option($event->getStreamPlatform()),
            'link' => $event->getLink(),
            'theme' => $event->getTheme(),
            'createdBy' => null !== $author && $author->isActive() ? $this->user($author) : null,
            'participants' => $participants,
            'participating' => null !== $event->participantOf($viewer),
            'full' => $event->isFull(),
            'over' => $event->isOver(),
            'canParticipate' => $rights['participate'],
            'canEdit' => $rights['edit'],
            'canDelete' => $rights['delete'],
        ];
    }

    /** @return array{value: string, label: string}|null */
    private static function option(GamePlatform|FilmPlatform|StreamPlatform|null $option): ?array
    {
        return null !== $option ? ['value' => $option->value, 'label' => $option->label()] : null;
    }

    /**
     * Film of an event, shown to every member.
     *
     * @return array{tmdbId: int, title: string, year: ?string, posterUrl: ?string}
     */
    public function film(Film $film): array
    {
        return [
            'tmdbId' => $film->getTmdbId(),
            'title' => $film->getTitle(),
            'year' => $film->getYear(),
            'posterUrl' => $film->getPosterUrl(),
        ];
    }

    /**
     * Full fiche, for the Cinéphiles.
     *
     * @return array<string, mixed>
     */
    public function filmDetails(Film $film): array
    {
        return $this->film($film) + [
            'originalTitle' => $film->getOriginalTitle(),
            'releaseDate' => $film->getReleaseDate(),
            'backdropUrl' => $film->getBackdropUrl(),
            'overview' => $film->getOverview(),
            'genres' => $film->getGenres(),
            'runtime' => $film->getRuntime(),
            'directors' => $film->getDirectors(),
            'cast' => $film->getCast(),
            'tmdbUrl' => 'https://www.themoviedb.org/movie/'.$film->getTmdbId(),
        ];
    }

    /**
     * @param list<GamePlatform> $platforms
     *
     * @return list<string>
     */
    public function platforms(array $platforms): array
    {
        return array_map(static fn (GamePlatform $p): string => $p->value, $platforms);
    }

    /** @return array{id: ?int, source: string, externalId: string, name: string, coverUrl: ?string, releaseDate: ?string, genres: list<string>, availablePlatforms: list<string>} */
    public function game(Game $game): array
    {
        return [
            'id' => $game->getId(),
            'source' => $game->getSource(),
            'externalId' => $game->getExternalId(),
            'name' => $game->getName(),
            'coverUrl' => $game->getCoverUrl(),
            'releaseDate' => $game->getReleaseDate(),
            'genres' => $game->getGenres(),
            'availablePlatforms' => $this->platforms($game->getAvailablePlatforms()),
        ];
    }

    /** @return array{id: ?int, source: string, externalId: string, name: string, coverUrl: ?string, releaseDate: ?string, genres: list<string>, availablePlatforms: list<string>, description: ?string, screenshots: list<array{thumbnail: string, full: string}>, developers: list<string>, website: ?string} */
    public function gameDetails(Game $game): array
    {
        return $this->game($game) + [
            'description' => $game->getDescription(),
            'screenshots' => $game->getScreenshots(),
            'developers' => $game->getDevelopers(),
            'website' => $game->getWebsite(),
        ];
    }

    /**
     * A game played by the current user.
     *
     * @return array{id: ?int, game: array{id: ?int, source: string, externalId: string, name: string, coverUrl: ?string, releaseDate: ?string, genres: list<string>, availablePlatforms: list<string>}, platforms: list<string>, addedAt: string}
     */
    public function userGame(UserGame $userGame): array
    {
        return [
            'id' => $userGame->getId(),
            'game' => $this->game($userGame->getGame()),
            'platforms' => $this->platforms($userGame->getPlatforms()),
            'addedAt' => $userGame->getAddedAt()->format(\DATE_ATOM),
        ];
    }

    /**
     * A clan member playing a game.
     *
     * @return array{user: array{id: ?int, username: string, displayName: string, role: string, avatarUrl: ?string}, platforms: list<string>}
     */
    public function player(UserGame $userGame): array
    {
        return [
            'user' => $this->user($userGame->getUser()),
            'platforms' => $this->platforms($userGame->getPlatforms()),
        ];
    }

    /** @return array{source: string, externalId: string, name: string, coverUrl: ?string, releaseDate: ?string, platforms: list<string>, inLibrary: bool} */
    public function catalogGame(CatalogGame $game, bool $inLibrary): array
    {
        return [
            'source' => $game->source,
            'externalId' => $game->externalId,
            'name' => $game->name,
            'coverUrl' => $game->coverUrl,
            'releaseDate' => $game->releaseDate,
            'platforms' => $this->platforms($game->platforms),
            'inLibrary' => $inLibrary,
        ];
    }

    /**
     * Presence of a member. "ageSeconds" lets clients expire the app status with their own
     * clock (no clock skew issue): it is stale AppStatus::TIMEOUT_SECONDS after the last heartbeat.
     *
     * @return array{userId: ?int, app: array{status: string, ageSeconds: ?int}, discord: ?array{username: ?string, avatarUrl: ?string, status: string, activity: ?string}}
     */
    public function presence(User $user): array
    {
        $status = AppStatus::of($user);
        $seen = $user->getLastSeenAt();

        return [
            'userId' => $user->getId(),
            'app' => [
                'status' => $status->value,
                'ageSeconds' => AppStatus::Offline !== $status && $seen ? time() - $seen->getTimestamp() : null,
            ],
            'discord' => $user->getDiscordId() ? [
                'username' => $user->getDiscordUsername(),
                'avatarUrl' => $user->getDiscordAvatarUrl(),
                'status' => $user->getDiscordStatus() ?? 'offline',
                'activity' => $user->getDiscordActivity(),
            ] : null,
        ];
    }

    /** @return array{id: ?int, title: string, joinUrl: string, createdBy: ?array{id: ?int, username: string, displayName: string, role: string, avatarUrl: ?string}, participants: list<array{id: ?int, username: string, displayName: string, role: string, avatarUrl: ?string}>, connectedUserIds: list<?int>, started: bool, createdAt: string} */
    public function call(VideoCall $call): array
    {
        $connected = $call->getConnectedDiscordIds();
        $participants = $call->getParticipants()->getValues();

        return [
            'id' => $call->getId(),
            'title' => $call->getTitle(),
            'joinUrl' => $call->getJoinUrl(),
            'createdBy' => $call->getCreatedBy() ? $this->user($call->getCreatedBy()) : null,
            'participants' => array_map($this->user(...), $participants),
            'connectedUserIds' => array_values(array_map(
                static fn (User $u): ?int => $u->getId(),
                array_filter($participants, static fn (User $u): bool => \in_array($u->getDiscordId(), $connected, true)),
            )),
            'started' => $call->isStarted(),
            'createdAt' => $call->getCreatedAt()->format(\DATE_ATOM),
        ];
    }
}
