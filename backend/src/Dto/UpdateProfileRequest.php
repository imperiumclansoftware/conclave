<?php

namespace App\Dto;

use App\Enum\GamerTag;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateProfileRequest
{
    /**
     * @param array<string, string> $gamerTags
     */
    public function __construct(
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: 50)]
        public string $displayName = '',
        #[Assert\Length(max: 280)]
        public ?string $bio = null,
        /** For the e-mails (password reset, notifications) */
        #[Assert\Email(normalizer: 'trim')]
        #[Assert\Length(max: 180)]
        public ?string $email = null,
        #[Assert\Collection(
            fields: [
                'psn' => new Assert\Optional([new Assert\Type('string'), new Assert\Length(max: 50)]),
                'xbox' => new Assert\Optional([new Assert\Type('string'), new Assert\Length(max: 50)]),
                'steam' => new Assert\Optional([new Assert\Type('string'), new Assert\Length(max: 50)]),
                'nintendo' => new Assert\Optional([new Assert\Type('string'), new Assert\Length(max: 50)]),
                'epic' => new Assert\Optional([new Assert\Type('string'), new Assert\Length(max: 50)]),
                'battlenet' => new Assert\Optional([new Assert\Type('string'), new Assert\Length(max: 50)]),
            ],
            allowExtraFields: false,
        )]
        public array $gamerTags = [],
    ) {
    }

    /** @return array<string, string> non-empty tags, in the canonical order */
    public function cleanGamerTags(): array
    {
        $tags = [];
        foreach (GamerTag::cases() as $network) {
            $value = trim((string) ($this->gamerTags[$network->value] ?? ''));
            if ('' !== $value) {
                $tags[$network->value] = $value;
            }
        }

        return $tags;
    }
}
