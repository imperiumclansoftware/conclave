<?php

namespace App\Enum;

/**
 * Permissions a machine client (OAuth2 client credentials) can be granted.
 */
enum OAuthScope: string
{
    /** Read the members who chose to appear on the clan's public site (Imperium Clan portal) */
    case PortalMembers = 'portal:members';

    /** OpenID Connect sign-in: identifies the member (sub) to the client */
    case OpenId = 'openid';

    /** With openid: display name and branches of the member in the ID token */
    case Profile = 'profile';

    /** Security role given to a request authenticated by a token carrying this scope */
    public function role(): string
    {
        return 'ROLE_OAUTH_'.strtoupper(str_replace(':', '_', $this->value));
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $s): string => $s->value, self::cases());
    }
}
