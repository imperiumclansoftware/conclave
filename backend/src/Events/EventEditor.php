<?php

namespace App\Events;

use App\Catalog\CatalogUnavailableException;
use App\Catalog\GameLibrary;
use App\Catalog\GameNotFoundException;
use App\Dto\EventRequest;
use App\Entity\Event;
use App\Entity\User;
use App\Films\FilmCatalogUnavailableException;
use App\Films\FilmLibrary;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/** Creates and updates events, checking the fields of their type. */
final readonly class EventEditor
{
    private const FIELD_LABELS = [
        'game' => 'le jeu',
        'gamePlatform' => 'la plateforme de jeu',
        'film' => 'le film',
        'filmPlatform' => 'la plateforme de visionnage',
        'streamPlatform' => 'la plateforme de diffusion',
        'link' => 'le lien',
        'theme' => 'le thème',
    ];

    public function __construct(
        private EventPermissions $permissions,
        private GameLibrary $games,
        private FilmLibrary $films,
    ) {
    }

    public function create(User $author, EventRequest $request): Event
    {
        $type = $request->type ?? throw new UnprocessableEntityHttpException('Choisissez le type d\'événement.');
        $branch = $this->permissions->branchFor($author, $type)
            ?? throw new AccessDeniedHttpException(\sprintf('Seuls les membres de la branche concernée créent un événement « %s ».', $type->label()));

        $event = new Event($branch, $type, $author);
        $this->apply($event, $request);

        return $event;
    }

    public function update(Event $event, EventRequest $request): void
    {
        $this->apply($event, $request);
    }

    private function apply(Event $event, EventRequest $request): void
    {
        $startsAt = $request->startsAt ?? throw new UnprocessableEntityHttpException('Indiquez la date et l\'heure de début.');
        $event->describe(
            trim(preg_replace('/\s+/', ' ', $request->title)),
            EventRequest::clean($request->description),
            $startsAt,
            $request->endsAt,
            $request->maxParticipants,
        );

        $values = [
            'game' => null !== $request->gameSource && null !== $request->gameExternalId ? true : null,
            'gamePlatform' => $request->gamePlatform,
            'film' => $request->tmdbId,
            'filmPlatform' => $request->filmPlatform,
            'streamPlatform' => $request->streamPlatform,
            'link' => EventRequest::clean($request->link),
            'theme' => EventRequest::clean($request->theme),
        ];
        foreach ($event->getType()->fields() as $field => $required) {
            if ($required && null === $values[$field]) {
                throw new UnprocessableEntityHttpException(\sprintf('Précisez %s.', self::FIELD_LABELS[$field]));
            }
        }

        $fields = $event->getType()->fields();
        try {
            $game = isset($fields['game']) && null !== $values['game'] ? $this->games->findOrFetch((string) $request->gameSource, (string) $request->gameExternalId) : null;
            $film = isset($fields['film']) && null !== $request->tmdbId ? $this->films->findOrFetch($request->tmdbId) : null;
        } catch (GameNotFoundException $e) {
            throw new UnprocessableEntityHttpException('Ce jeu est introuvable.', $e);
        } catch (\DomainException $e) {
            throw new UnprocessableEntityHttpException($e->getMessage(), $e);
        } catch (CatalogUnavailableException|FilmCatalogUnavailableException $e) {
            throw new ServiceUnavailableHttpException(null, 'Le catalogue est injoignable, réessayez plus tard.', $e);
        }

        $event->specify($game, $request->gamePlatform, $film, $request->filmPlatform, $request->streamPlatform, $values['link'], $values['theme']);
    }
}
