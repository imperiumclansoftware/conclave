<?php

namespace App\Entity;

use App\Repository\BranchLinkRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Favourite link of a branch, shared by its members in the links sidebar.
 */
#[ORM\Entity(repositoryClass: BranchLinkRepository::class)]
class BranchLink
{
    public const TITLE_MAX_LENGTH = 80;
    public const URL_MAX_LENGTH = 500;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Branch $branch,
        /** Null once the member has left the clan */
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
        private ?User $addedBy,
        #[ORM\Column(length: self::TITLE_MAX_LENGTH)]
        private string $title,
        #[ORM\Column(length: self::URL_MAX_LENGTH)]
        private string $url,
    ) {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBranch(): Branch
    {
        return $this->branch;
    }

    public function getAddedBy(): ?User
    {
        return $this->addedBy;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
