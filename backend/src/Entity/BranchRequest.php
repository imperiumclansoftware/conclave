<?php

namespace App\Entity;

use App\Repository\BranchRequestRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * A member asks to join a branch whose membership is validated by a leader.
 */
#[ORM\Entity(repositoryClass: BranchRequestRepository::class)]
#[ORM\UniqueConstraint(name: 'branch_request_unique', columns: ['branch_id', 'user_id'])]
class BranchRequest
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        #[ORM\ManyToOne(inversedBy: 'requests')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Branch $branch,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private User $user,
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

    public function getUser(): User
    {
        return $this->user;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
