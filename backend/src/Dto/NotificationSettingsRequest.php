<?php

namespace App\Dto;

final readonly class NotificationSettingsRequest
{
    public function __construct(
        /** E-mail when an event is organised on one of the member's games */
        public bool $gameEventEmails = true,
    ) {
    }
}
