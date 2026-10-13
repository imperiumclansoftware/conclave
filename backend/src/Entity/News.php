<?php

namespace App\Entity;

use App\Repository\NewsRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * News of the clan, written by its leaders and by the members of a publishing branch.
 */
#[ORM\Entity(repositoryClass: NewsRepository::class)]
class News
{
    public const TITLE_MAX_LENGTH = 120;
    public const BODY_MAX_LENGTH = 10000;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    /** Stored by NewsImageStorage; null for the default visual */
    #[ORM\Column(length: 64, nullable: true)]
    private ?string $imageFile = null;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Clan $clan,
        /** Null once the author has left the clan */
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
        private ?User $author,
        #[ORM\Column(length: self::TITLE_MAX_LENGTH)]
        private string $title,
        #[ORM\Column(type: Types::TEXT)]
        private string $body,
    ) {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getClan(): Clan
    {
        return $this->clan;
    }

    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getImageFile(): ?string
    {
        return $this->imageFile;
    }

    public function setImageFile(?string $imageFile): void
    {
        $this->imageFile = $imageFile;
    }

    public function edit(string $title, string $body): void
    {
        $this->title = $title;
        $this->body = $body;
        $this->updatedAt = new \DateTimeImmutable();
    }
}
