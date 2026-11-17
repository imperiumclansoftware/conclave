<?php

namespace App\Command;

use App\Enum\OAuthScope;
use App\OAuth\ClientCredentials;
use App\OAuth\RedirectUris;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Creates a machine client (OAuth2 client credentials), e.g. for the clan's public portal.
 * The secret is printed once: only its hash is kept.
 */
#[AsCommand(name: 'app:oauth:client:create', description: 'Creates an OAuth2 machine client (client credentials) and prints its secret once')]
final class OAuthClientCreateCommand
{
    public function __construct(private readonly ClientCredentials $credentials)
    {
    }

    /**
     * @param list<string> $scope
     * @param list<string> $redirectUri
     */
    public function __invoke(
        SymfonyStyle $io,
        #[Option('Libellé du client, ex. « Portail »')] string $name = '',
        #[Option('Portée autorisée (répétable) : portal:members, openid, profile')] array $scope = [],
        #[Option('Adresse de retour de la connexion OpenID Connect (répétable)', name: 'redirect-uri')] array $redirectUri = [],
    ): int {
        $name = trim($name);
        if ('' === $name || mb_strlen($name) > 100) {
            $io->error('Indiquez un libellé de 1 à 100 caractères : --name="Portail".');

            return Command::FAILURE;
        }
        $scopes = [];
        foreach ($scope as $value) {
            $known = OAuthScope::tryFrom($value);
            if (null === $known) {
                $io->error(\sprintf('Portée inconnue « %s ». Portées possibles : %s.', $value, implode(', ', OAuthScope::values())));

                return Command::FAILURE;
            }
            $scopes[] = $known;
        }
        if ([] === $scopes) {
            $io->error('Indiquez au moins une portée : --scope=portal:members.');

            return Command::FAILURE;
        }
        if (null !== $error = RedirectUris::check($redirectUri, $scopes)) {
            $io->error($error);

            return Command::FAILURE;
        }

        [$client, $secret] = $this->credentials->createClient($name, $scopes, $redirectUri);

        $io->success(\sprintf('Client « %s » créé (n° %d).', $client->getName(), $client->getId()));
        $io->definitionList(
            ['client_id' => $client->getClientId()],
            ['client_secret' => $secret],
            ['portées' => implode(' ', $client->getScopes())],
            ['retours' => implode("\n", $client->getRedirectUris()) ?: '—'],
        );
        $io->warning('Notez le secret maintenant : il ne sera plus jamais affiché (seule son empreinte est conservée).');

        return Command::SUCCESS;
    }
}
