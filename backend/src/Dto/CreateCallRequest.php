<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateCallRequest
{
    /**
     * @param list<int> $userIds members to invite
     */
    public function __construct(
        #[Assert\Count(min: 1, max: 24, minMessage: 'Choisissez au moins un membre à inviter.')]
        #[Assert\All([new Assert\Type('integer'), new Assert\Positive()])]
        public array $userIds = [],
        #[Assert\Length(max: 80)]
        public ?string $title = null,
    ) {
    }
}
