<?php

namespace App\Controller;

use App\Entity\User;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Validates entity-level constraints (e.g. unique username) and reports them
 * in the same format as #[MapRequestPayload] validation errors.
 */
final class UserValidation
{
    public static function assertValid(ValidatorInterface $validator, User $user): void
    {
        $violations = $validator->validate($user);
        if (\count($violations) > 0) {
            throw new UnprocessableEntityHttpException('Validation failed.', new ValidationFailedException($user, $violations));
        }
    }
}
