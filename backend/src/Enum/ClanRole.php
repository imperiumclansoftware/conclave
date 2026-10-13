<?php

namespace App\Enum;

enum ClanRole: string
{
    case Leader = 'leader';
    case Member = 'member';

    public function securityRole(): string
    {
        return 'ROLE_'.strtoupper($this->value);
    }
}
