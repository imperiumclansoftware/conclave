<?php

namespace App\Dto;

use App\Entity\News;
use Symfony\Component\Validator\Constraints as Assert;

/** Sent as multipart/form-data, with the optional image beside it. */
final readonly class NewsRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Donnez un titre à la news.', normalizer: 'trim')]
        #[Assert\Length(max: News::TITLE_MAX_LENGTH, normalizer: 'trim')]
        public string $title = '',
        #[Assert\NotBlank(message: 'Écrivez le texte de la news.', normalizer: 'trim')]
        #[Assert\Length(max: News::BODY_MAX_LENGTH, normalizer: 'trim')]
        public string $body = '',
        /** On update: back to the default visual */
        public bool $removeImage = false,
    ) {
    }

    public function cleanTitle(): string
    {
        return trim(preg_replace('/\s+/', ' ', $this->title));
    }

    /** Line breaks kept, at most one blank line between paragraphs */
    public function cleanBody(): string
    {
        $body = str_replace(["\r\n", "\r"], "\n", trim($this->body));

        return preg_replace("/\n{3,}/", "\n\n", $body);
    }
}
