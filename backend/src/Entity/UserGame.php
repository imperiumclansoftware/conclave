<?php

namespace App\Entity;

use App\Enum\GamePlatform;
use App\Repository\UserGameRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A game played by a member, on one or more platforms.
 */
#[ORM\Entity(repositoryClass: UserGameRepository::class)]
#[ORM\UniqueConstraint(columns: ['user_id', 'game_id'])]
class UserGame
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** @var list<string> GamePlatform values */
    #[ORM\Column(type: Types::JSON)]
    private array $platforms = [];

    #[ORM\Column]
    private \DateTimeImmutable $addedAt;

    /**
     * @param list<GamePlatform> $platforms
     */
    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private User $user,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Game $game,
        array $platforms,
    ) {
        $this->setPlatforms($platforms);
        $this->addedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getGame(): Game
    {
        return $this->game;
    }

    /** @return list<GamePlatform> */
    public function getPlatforms(): array
    {
        return GamePlatform::fromValues($this->platforms);
    }

    /** @param list<GamePlatform> $platforms */
    public function setPlatforms(array $platforms): void
    {
        $this->platforms = array_map(static fn (GamePlatform $p): string => $p->value, GamePlatform::fromValues(
            array_map(static fn (GamePlatform $p): string => $p->value, $platforms),
        ));
    }

    public function getAddedAt(): \DateTimeImmutable
    {
        return $this->addedAt;
    }
}
