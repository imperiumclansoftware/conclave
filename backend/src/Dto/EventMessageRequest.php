<?php

namespace App\Dto;

use App\Entity\EventMessage;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class EventMessageRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Le message est vide.', normalizer: 'trim')]
        #[Assert\Length(max: EventMessage::MAX_LENGTH, maxMessage: 'Le message dépasse {{ limit }} caractères.')]
        public string $content = '',
    ) {
    }
}
