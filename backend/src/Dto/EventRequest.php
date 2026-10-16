<?php

namespace App\Dto;

use App\Entity\Event;
use App\Enum\EventType;
use App\Enum\FilmKind;
use App\Enum\FilmPlatform;
use App\Enum\GamePlatform;
use App\Enum\StreamPlatform;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/** Creation or update of an event (the type cannot change afterwards). */
final readonly class EventRequest
{
    public function __construct(
        public ?EventType $type = null,
        #[Assert\NotBlank(message: 'Donnez un titre à l\'événement.', normalizer: 'trim')]
        #[Assert\Length(max: Event::TITLE_MAX_LENGTH, normalizer: 'trim')]
        public string $title = '',
        #[Assert\Length(max: Event::DESCRIPTION_MAX_LENGTH, normalizer: 'trim')]
        public ?string $description = null,
        #[Assert\NotNull(message: 'Indiquez la date et l\'heure de début.')]
        public ?\DateTimeImmutable $startsAt = null,
        public ?\DateTimeImmutable $endsAt = null,
        #[Assert\Range(min: 2, max: 500, notInRangeMessage: 'Le nombre de places va de {{ min }} à {{ max }}.')]
        public ?int $maxParticipants = null,
        /** Game: from the catalog search (source and identifier) */
        #[Assert\Choice(choices: ['steam', 'rawg'])]
        public ?string $gameSource = null,
        #[Assert\Length(max: 50)]
        public ?string $gameExternalId = null,
        public ?GamePlatform $gamePlatform = null,
        /** Film or series: from the TMDB search */
        #[Assert\Positive]
        public ?int $tmdbId = null,
        public FilmKind $filmKind = FilmKind::Movie,
        public ?FilmPlatform $filmPlatform = null,
        public ?StreamPlatform $streamPlatform = null,
        #[Assert\Url(message: 'Adresse invalide : elle commence par https:// ou http://.', protocols: ['http', 'https'], requireTld: true, normalizer: 'trim')]
        #[Assert\Length(max: 500)]
        public ?string $link = null,
        #[Assert\Length(max: 120, normalizer: 'trim')]
        public ?string $theme = null,
    ) {
    }

    #[Assert\Callback]
    public function validateDates(ExecutionContextInterface $context): void
    {
        if (null !== $this->startsAt && null !== $this->endsAt && $this->endsAt <= $this->startsAt) {
            $context->buildViolation('La fin doit être après le début.')->atPath('endsAt')->addViolation();
        }
    }

    public static function clean(?string $text): ?string
    {
        $text = trim((string) $text);

        return '' !== $text ? $text : null;
    }
}
