<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/** Length of the text written with the editor, formatting and links left out. */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
final class RichTextLength extends Constraint
{
    public function __construct(
        public int $max,
        public bool $required = false,
        public string $requiredMessage = 'Ce texte est vide.',
        public string $maxMessage = 'Le texte dépasse {{ limit }} caractères.',
        /** Markup is allowed this many times the text length (links, formatting) */
        public int $markupFactor = 5,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct(null, $groups, $payload);
    }
}
