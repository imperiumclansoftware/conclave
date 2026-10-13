<?php

namespace App\Dto;

use App\Enum\ClanRole;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class ChangeRoleRequest
{
    public function __construct(
        #[Assert\NotNull]
        public ?ClanRole $role = null,
    ) {
    }
}
