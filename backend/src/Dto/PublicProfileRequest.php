<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class PublicProfileRequest
{
    public function __construct(
        /** Appear on the clan's public site (display name, presentation, branches, avatar) */
        #[Assert\NotNull]
        public ?bool $visible = null,
    ) {
    }
}
