<?php

namespace App\Command;

use App\Enum\ClanRole;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Recovery tool: makes a member leader (and approves their account), e.g. when no leader can log in anymore.
 */
#[AsCommand(name: 'app:user:leader', description: 'Makes a member leader of the clan (or member again with --revoke)')]
final class UserLeaderCommand
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('Identifiant de connexion du membre')] string $username,
        #[Option('Redevenir simple membre')] bool $revoke = false,
    ): int {
        $user = $this->users->findOneBy(['username' => $username]);
        if (null === $user) {
            $io->error(\sprintf('Aucun membre « %s ».', $username));

            return Command::FAILURE;
        }

        if (!$user->isActive()) {
            $user->approve();
        }
        $user->setClanRole($revoke ? ClanRole::Member : ClanRole::Leader);
        $this->em->flush();
        $io->success(\sprintf('%s est maintenant %s.', $user->getDisplayName(), $revoke ? 'membre' : 'dirigeant'));

        return Command::SUCCESS;
    }
}
