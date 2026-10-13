<?php

namespace App\Discord;

use App\Settings\AppSettings;

/**
 * Credentials of the Discord application (https://discord.com/developers/applications),
 * configured in the administration or in the environment.
 */
final readonly class DiscordConfig
{
    public function __construct(
        private AppSettings $settings,
    ) {
    }

    public function clientId(): ?string
    {
        return $this->settings->get(AppSettings::DISCORD_CLIENT_ID);
    }

    public function clientSecret(): ?string
    {
        return $this->settings->get(AppSettings::DISCORD_CLIENT_SECRET);
    }

    public function botToken(): ?string
    {
        return $this->settings->get(AppSettings::DISCORD_BOT_TOKEN);
    }

    public function redirectUri(): ?string
    {
        return $this->settings->get(AppSettings::DISCORD_REDIRECT_URI);
    }

    public function isEnabled(): bool
    {
        return null !== $this->clientId() && null !== $this->clientSecret()
            && null !== $this->botToken() && null !== $this->redirectUri();
    }
}
