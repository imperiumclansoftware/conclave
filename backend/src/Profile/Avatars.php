<?php

namespace App\Profile;

use App\Entity\User;
use App\Enum\AvatarSource;

/**
 * Resolves the avatar shown for a member, among the sources they have set up.
 */
final class Avatars
{
    /** Order used when the member did not choose a source */
    private const AUTO_ORDER = [AvatarSource::Upload, AvatarSource::Gravatar, AvatarSource::Discord];

    /** @return array<string, string> source => URL, for the sources the member has set up */
    public static function available(User $user): array
    {
        $urls = [];
        if (null !== $user->getAvatarFile()) {
            // Relative to the app's base URL, like every API call of the client
            $urls[AvatarSource::Upload->value] = 'api/avatars/'.$user->getAvatarFile();
        }
        if (null !== $user->getGravatarHash()) {
            $urls[AvatarSource::Gravatar->value] = Gravatar::url($user->getGravatarHash());
        }
        if (null !== $discord = $user->getDiscordAvatarUrl()) {
            $urls[AvatarSource::Discord->value] = $discord;
        }

        return $urls;
    }

    /** The source actually shown: the chosen one if still available, else the first available. */
    public static function effectiveSource(User $user): AvatarSource
    {
        $available = self::available($user);
        $chosen = $user->getAvatarSource();
        if (AvatarSource::Initials === $chosen || (null !== $chosen && isset($available[$chosen->value]))) {
            return $chosen;
        }
        foreach (self::AUTO_ORDER as $source) {
            if (isset($available[$source->value])) {
                return $source;
            }
        }

        return AvatarSource::Initials;
    }

    /** null: the client shows the initial */
    public static function url(User $user): ?string
    {
        return self::available($user)[self::effectiveSource($user)->value] ?? null;
    }
}
