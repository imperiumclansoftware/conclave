<?php

namespace App\Command;

use App\Repository\ClanRepository;
use App\Setup\SetupCode;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:setup:code', description: 'Shows the code asked by the installation wizard')]
final readonly class SetupCodeCommand
{
    public function __construct(
        private SetupCode $code,
        private ClanRepository $clans,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        if (null !== $clan = $this->clans->findMain()) {
            $io->note(\sprintf('L\'application est déjà installée (clan « %s »).', $clan->getName()));

            return Command::SUCCESS;
        }

        $io->success('Code d\'installation : '.$this->code->current());
        $io->text('Saisissez-le dans l\'assistant d\'installation (page /installation) pour créer le premier dirigeant.');

        return Command::SUCCESS;
    }
}
