<?php

namespace App\Command;

use App\Enum\OAuthScope;
use App\OAuth\RedirectUris;
use App\Repository\OAuthClientRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Changes the scopes and redirect URIs of a machine client (its secret does not change), e.g. to add
 * the OpenID Connect sign-in to the portal's existing client.
 */
#[AsCommand(name: 'app:oauth:client:update', description: 'Replaces the scopes and/or redirect URIs of an OAuth2 client (secret unchanged)')]
final class OAuthClientUpdateCommand
{
    public function __construct(
        private readonly OAuthClientRepository $clients,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * @param list<string> $scope
     * @param list<string> $redirectUri
     */
    public function __invoke(
        SymfonyStyle $io,
        #[Argument('Numéro (voir app:oauth:client:list) ou client_id')] string $id,
        #[Option('Portées (répétable), remplacent les actuelles : portal:members, openid, profile')] array $scope = [],
        #[Option('Adresses de retour (répétable), remplacent les actuelles', name: 'redirect-uri')] array $redirectUri = [],
    ): int {
        $client = $this->clients->findByReference($id);
        if (null === $client) {
            $io->error(\sprintf('Aucun client « %s ».', $id));

            return Command::FAILURE;
        }
        $scopes = [];
        foreach ([] === $scope ? $client->getScopes() : $scope as $value) {
            $known = OAuthScope::tryFrom($value);
            if (null === $known) {
                $io->error(\sprintf('Portée inconnue « %s ». Portées possibles : %s.', $value, implode(', ', OAuthScope::values())));

                return Command::FAILURE;
            }
            $scopes[] = $known;
        }
        $uris = [] === $redirectUri ? $client->getRedirectUris() : $redirectUri;
        if (null !== $error = RedirectUris::check($uris, $scopes)) {
            $io->error($error);

            return Command::FAILURE;
        }

        $client->setScopes(array_map(static fn (OAuthScope $s): string => $s->value, $scopes));
        $client->setRedirectUris($uris);
        $this->em->flush();
        $io->success(\sprintf('Client « %s » : portées %s ; retours %s.', $client->getName(), implode(' ', $client->getScopes()), implode(', ', $client->getRedirectUris()) ?: '—'));

        return Command::SUCCESS;
    }
}
