<?php

namespace App\Dto;

use App\Entity\BranchLink;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class BranchLinkRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Indiquez l\'adresse du lien.', normalizer: 'trim')]
        #[Assert\Url(message: 'Adresse invalide : elle commence par https:// ou http://.', protocols: ['http', 'https'], requireTld: true, normalizer: 'trim')]
        #[Assert\Length(max: BranchLink::URL_MAX_LENGTH, normalizer: 'trim')]
        public string $url = '',
        /** The address' host when empty */
        #[Assert\Length(max: BranchLink::TITLE_MAX_LENGTH, normalizer: 'trim')]
        public ?string $title = null,
    ) {
    }

    public function cleanUrl(): string
    {
        return trim($this->url);
    }

    public function cleanTitle(): string
    {
        $title = trim(preg_replace('/\s+/', ' ', (string) $this->title));

        return '' !== $title ? $title : (string) preg_replace('/^www\./', '', (string) parse_url($this->cleanUrl(), \PHP_URL_HOST));
    }
}
