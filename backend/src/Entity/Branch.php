<?php

namespace App\Entity;

use App\Enum\BranchCrest;
use App\Repository\BranchRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Part of the clan, from the catalogue of the app (one per coat of arms, see BranchCatalog).
 * Leaders activate the ones they want; members join the active branches, as many as they like.
 */
#[ORM\Entity(repositoryClass: BranchRepository::class)]
#[ORM\UniqueConstraint(name: 'branch_clan_name', columns: ['clan_id', 'name'])]
#[ORM\UniqueConstraint(name: 'branch_clan_crest', columns: ['clan_id', 'crest'])]
class Branch
{
    public const NAME_MAX_LENGTH = 50;
    public const DESCRIPTION_MAX_LENGTH = 280;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, User> */
    #[ORM\ManyToMany(targetEntity: User::class)]
    #[ORM\JoinTable(name: 'branch_member')]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(onDelete: 'CASCADE')]
    #[ORM\OrderBy(['displayName' => 'ASC'])]
    private Collection $members;

    /** @var Collection<int, BranchRequest> members waiting for a leader's approval */
    #[ORM\OneToMany(targetEntity: BranchRequest::class, mappedBy: 'branch')]
    private Collection $requests;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Clan $clan,
        #[ORM\Column(length: self::NAME_MAX_LENGTH)]
        private string $name,
        #[ORM\Column(length: self::DESCRIPTION_MAX_LENGTH, nullable: true)]
        private ?string $description = null,
        /** Coat of arms; null for the plain banner */
        #[ORM\Column(length: 20, nullable: true, enumType: BranchCrest::class)]
        private ?BranchCrest $crest = null,
        /** Its members can publish the clan's news */
        #[ORM\Column(options: ['default' => false])]
        private bool $publishesNews = false,
        /** Joining needs a leader's approval */
        #[ORM\Column(options: ['default' => false])]
        private bool $approvalRequired = false,
        /** Inactive: hidden from the members, who keep their membership for its reactivation */
        #[ORM\Column(options: ['default' => true])]
        private bool $active = true,
    ) {
        $this->createdAt = new \DateTimeImmutable();
        $this->members = new ArrayCollection();
        $this->requests = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getClan(): Clan
    {
        return $this->clan;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getCrest(): ?BranchCrest
    {
        return $this->crest;
    }

    public function publishesNews(): bool
    {
        return $this->publishesNews;
    }

    public function isApprovalRequired(): bool
    {
        return $this->approvalRequired;
    }

    /** @return Collection<int, BranchRequest> */
    public function getRequests(): Collection
    {
        return $this->requests;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function update(string $name, ?string $description, bool $publishesNews, bool $approvalRequired): void
    {
        $this->name = $name;
        $this->description = $description;
        $this->publishesNews = $publishesNews;
        $this->approvalRequired = $approvalRequired;
    }

    /** @return Collection<int, User> active members (pending sign-ups excluded) */
    public function getMembers(): Collection
    {
        return $this->members->filter(static fn (User $user): bool => $user->isActive());
    }

    public function hasMember(User $user): bool
    {
        return $this->members->contains($user);
    }

    public function join(User $user): void
    {
        if (!$this->members->contains($user)) {
            $this->members->add($user);
        }
    }

    public function leave(User $user): void
    {
        $this->members->removeElement($user);
    }
}
