<?php

namespace App\OAuth;

use App\Enum\OAuthScope;

/** Rules for the redirect URIs of a client (OpenID Connect sign-in). */
final class RedirectUris
{
    /**
     * @param list<string>     $uris
     * @param list<OAuthScope> $scopes
     *
     * @return string|null error message, null when valid
     */
    public static function check(array $uris, array $scopes): ?string
    {
        if (\in_array(OAuthScope::OpenId, $scopes, true) && [] === $uris) {
            return 'La portée openid exige au moins une adresse de retour : --redirect-uri=https://portail.exemple.fr/api/auth/conclave/callback.';
        }
        foreach ($uris as $uri) {
            $parts = parse_url($uri);
            $local = \is_array($parts) && \in_array($parts['host'] ?? '', ['localhost', '127.0.0.1'], true);
            if (!\is_array($parts) || !isset($parts['host']) || isset($parts['fragment'])
                || !('https' === ($parts['scheme'] ?? '') || ($local && 'http' === ($parts['scheme'] ?? '')))) {
                return \sprintf('Adresse de retour invalide « %s » : URL https absolue sans #fragment (http accepté pour localhost).', $uri);
            }
        }

        return null;
    }
}
