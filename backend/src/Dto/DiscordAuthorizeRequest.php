<?php

namespace App\Dto;

use App\Discord\DiscordOAuth;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class DiscordAuthorizeRequest
{
    public function __construct(
        #[Assert\Choice(choices: [DiscordOAuth::LINK, DiscordOAuth::INSTALL])]
        public string $purpose = DiscordOAuth::LINK,
    ) {
    }
}
