<?php

namespace App\Entity;

use App\Catalog\CatalogGameDetails;
use App\Enum\GamePlatform;
use App\Repository\GameRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A game as described by an external catalog (Steam, RAWG), cached locally.
 */
#[ORM\Entity(repositoryClass: GameRepository::class)]
#[ORM\UniqueConstraint(columns: ['source', 'external_id'])]
class Game
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $coverUrl = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    /** @var list<array{thumbnail: string, full: string}> */
    #[ORM\Column(type: Types::JSON)]
    private array $screenshots = [];

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $genres = [];

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $developers = [];

    /**
     * Platforms the game is released on, according to the catalog (GamePlatform values).
     *
     * @var list<string>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $availablePlatforms = [];

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $releaseDate = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $website = null;

    #[ORM\Column]
    private \DateTimeImmutable $fetchedAt;

    public function __construct(
        #[ORM\Column(length: 10)]
        private string $source,
        #[ORM\Column(length: 50)]
        private string $externalId,
        CatalogGameDetails $details,
    ) {
        $this->refresh($details);
    }

    public function refresh(CatalogGameDetails $details): void
    {
        $this->name = $details->name;
        $this->coverUrl = $details->coverUrl;
        $this->description = $details->description;
        $this->screenshots = $details->screenshots;
        $this->genres = $details->genres;
        $this->developers = $details->developers;
        $this->availablePlatforms = array_map(static fn (GamePlatform $p): string => $p->value, $details->platforms);
        $this->releaseDate = $details->releaseDate;
        $this->website = $details->website;
        $this->fetchedAt = new \DateTimeImmutable();
    }

    public function isStale(): bool
    {
        return $this->fetchedAt < new \DateTimeImmutable('-30 days');
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function getExternalId(): string
    {
        return $this->externalId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCoverUrl(): ?string
    {
        return $this->coverUrl;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    /** @return list<array{thumbnail: string, full: string}> */
    public function getScreenshots(): array
    {
        return $this->screenshots;
    }

    /** @return list<string> */
    public function getGenres(): array
    {
        return $this->genres;
    }

    /** @return list<string> */
    public function getDevelopers(): array
    {
        return $this->developers;
    }

    /** @return list<GamePlatform> */
    public function getAvailablePlatforms(): array
    {
        return GamePlatform::fromValues($this->availablePlatforms);
    }

    public function getReleaseDate(): ?string
    {
        return $this->releaseDate;
    }

    public function getWebsite(): ?string
    {
        return $this->website;
    }
}
