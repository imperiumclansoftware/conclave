<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\Compound;

/** Picture of a news. */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::TARGET_PARAMETER)]
final class NewsImage extends Compound
{
    protected function getConstraints(array $options): array
    {
        return [
            new Assert\Image(
                maxSize: '8M',
                mimeTypes: ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
                minWidth: 200,
                minHeight: 100,
                maxWidth: 8000,
                maxHeight: 8000,
                mimeTypesMessage: 'Formats acceptés : JPEG, PNG, WebP ou GIF.',
            ),
        ];
    }
}
