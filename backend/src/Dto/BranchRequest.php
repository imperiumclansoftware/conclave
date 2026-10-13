<?php

namespace App\Dto;

use App\Entity\Branch;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class BranchRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Donnez un nom à la branche.', normalizer: 'trim')]
        #[Assert\Length(max: Branch::NAME_MAX_LENGTH, normalizer: 'trim')]
        public string $name = '',
        #[Assert\Length(max: Branch::DESCRIPTION_MAX_LENGTH, normalizer: 'trim')]
        public ?string $description = null,
        /** Its members can publish the clan's news */
        public bool $publishesNews = false,
        /** Joining needs a leader's approval */
        public bool $approvalRequired = false,
    ) {
    }

    public function cleanName(): string
    {
        return trim(preg_replace('/\s+/', ' ', $this->name));
    }

    public function cleanDescription(): ?string
    {
        return trim((string) $this->description) ?: null;
    }
}
