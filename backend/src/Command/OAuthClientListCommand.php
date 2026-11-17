<?php

namespace App\Command;

use App\Repository\OAuthClientRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:oauth:client:list', description: 'Lists the OAuth2 machine clients')]
final class OAuthClientListCommand
{
    public function __construct(private readonly OAuthClientRepository $clients)
    {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $clients = $this->clients->findBy([], ['id' => 'ASC']);
        if ([] === $clients) {
            $io->note('Aucun client. Créez-en un avec app:oauth:client:create.');

            return Command::SUCCESS;
        }

        $date = static fn (?\DateTimeImmutable $d): string => $d?->format('d/m/Y H:i') ?? '—';
        $io->table(
            ['N°', 'Libellé', 'client_id', 'Portées', 'Retours', 'État', 'Créé le', 'Dernière utilisation'],
            array_map(static fn ($c): array => [
                $c->getId(),
                $c->getName(),
                $c->getClientId(),
                implode(' ', $c->getScopes()),
                implode("\n", $c->getRedirectUris()) ?: '—',
                $c->isActive() ? 'actif' : 'révoqué le '.$date($c->getRevokedAt()),
                $date($c->getCreatedAt()),
                $date($c->getLastUsedAt()),
            ], $clients),
        );

        return Command::SUCCESS;
    }
}
