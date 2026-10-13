<?php

namespace App\Dto;

/**
 * Validation rules shared by every endpoint creating a user.
 */
final class UserInput
{
    public const USERNAME_PATTERN = '/^[a-z0-9._-]{3,30}$/';
    public const USERNAME_MESSAGE = 'L\'identifiant doit faire 3 à 30 caractères : lettres minuscules, chiffres, ".", "_" ou "-".';
    public const PASSWORD_MIN_LENGTH = 8;
}
