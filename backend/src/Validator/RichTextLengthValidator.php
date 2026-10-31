<?php

namespace App\Validator;

use App\Text\RichText;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class RichTextLengthValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof RichTextLength) {
            throw new UnexpectedTypeException($constraint, RichTextLength::class);
        }

        $html = (string) $value;
        $length = mb_strlen(RichText::plain($html));
        if (0 === $length) {
            if ($constraint->required) {
                $this->context->buildViolation($constraint->requiredMessage)->addViolation();
            }

            return;
        }
        if ($length > $constraint->max || mb_strlen($html) > $constraint->max * $constraint->markupFactor) {
            $this->context->buildViolation($constraint->maxMessage)
                ->setParameter('{{ limit }}', (string) $constraint->max)
                ->addViolation();
        }
    }
}
