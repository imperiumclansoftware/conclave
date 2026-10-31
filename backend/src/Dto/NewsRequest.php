<?php

namespace App\Dto;

use App\Entity\News;
use App\Validator\RichTextLength;
use Symfony\Component\Validator\Constraints as Assert;

/** Sent as multipart/form-data, with the optional image beside it. */
final readonly class NewsRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Donnez un titre à la news.', normalizer: 'trim')]
        #[Assert\Length(max: News::TITLE_MAX_LENGTH, normalizer: 'trim')]
        public string $title = '',
        /** HTML of the editor (or plain text) */
        #[RichTextLength(max: News::BODY_MAX_LENGTH, required: true, requiredMessage: 'Écrivez le texte de la news.')]
        public string $body = '',
        /** On update: back to the default visual */
        public bool $removeImage = false,
    ) {
    }

    public function cleanTitle(): string
    {
        return trim(preg_replace('/\s+/', ' ', $this->title));
    }
}
