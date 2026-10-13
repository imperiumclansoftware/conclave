<?php

namespace App\Dto;

use App\Enum\AvatarSource;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class AvatarSourceRequest
{
    public function __construct(
        #[Assert\NotNull]
        public ?AvatarSource $source = null,
    ) {
    }
}
