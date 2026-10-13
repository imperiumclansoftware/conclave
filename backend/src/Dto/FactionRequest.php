<?php

namespace App\Dto;

use App\Enum\Faction;

final readonly class FactionRequest
{
    public function __construct(
        /** null: follow the device's light/dark setting */
        public ?Faction $faction = null,
    ) {
    }
}
