<?php

namespace App\Command;

use App\OAuth\ClientCredentials;
use App\Repository\OAuthClientRepository;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Revokes a machine client: its current tokens stop working at once and it cannot get new ones.
 */
#[AsCommand(name: 'app:oauth:client:revoke', description: 'Revokes an OAuth2 machine client and its tokens')]
final class OAuthClientRevokeCommand
{
    public function __construct(
        private readonly OAuthClientRepository $clients,
        private readonly ClientCredentials $credentials,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('Numéro (voir app:oauth:client:list) ou client_id')] string $id,
    ): int {
        $client = $this->clients->findByReference($id);
        if (null === $client) {
            $io->error(\sprintf('Aucun client « %s ».', $id));

            return Command::FAILURE;
        }
        if (!$client->isActive()) {
            $io->note(\sprintf('Le client « %s » est déjà révoqué.', $client->getName()));

            return Command::SUCCESS;
        }

        $this->credentials->revoke($client);
        $io->success(\sprintf('Client « %s » révoqué : ses jetons ne fonctionnent plus.', $client->getName()));

        return Command::SUCCESS;
    }
}
