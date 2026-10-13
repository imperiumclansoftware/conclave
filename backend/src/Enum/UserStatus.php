<?php

namespace App\Enum;

enum UserStatus: string
{
    /** Signed up, waiting for a leader to approve the account */
    case Pending = 'pending';
    case Active = 'active';
}
