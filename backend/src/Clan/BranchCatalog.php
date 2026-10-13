<?php

namespace App\Clan;

use App\Entity\Branch;
use App\Entity\Clan;
use App\Enum\BranchCrest;
use App\Repository\BranchRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Branches offered by the app, one per coat of arms. Leaders do not create or delete branches:
 * they activate the ones they want, and may rename them or change their options.
 */
final readonly class BranchCatalog
{
    /** Coat of arms => [name, description, members publish the news, joined on approval] */
    private const DEFINITIONS = [
        'players' => ['Joueurs', null, false, false],
        'developers' => ['Développeurs', null, false, false],
        'communicants' => ['Communicants', 'Rédigent et publient les news du clan.', true, true],
        'cinephiles' => ['Cinéphiles', null, false, false],
    ];

    public function __construct(
        private BranchRepository $branches,
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * Adds the branches of the catalogue the clan does not have yet, inactive.
     *
     * @return list<Branch> all the branches of the clan, active or not
     */
    public function sync(Clan $clan): array
    {
        $existing = $this->branches->findByClan($clan, activeOnly: false);
        $crests = array_map(static fn (Branch $b): ?BranchCrest => $b->getCrest(), $existing);
        $added = false;
        foreach (BranchCrest::cases() as $crest) {
            if (\in_array($crest, $crests, true)) {
                continue;
            }
            [$name, $description, $publishesNews, $approvalRequired] = self::DEFINITIONS[$crest->value];
            // A branch renamed with this name keeps it: the catalogue one gets a suffix
            if (null !== $this->branches->findOneByName($clan, $name)) {
                $name .= ' (catalogue)';
            }
            $this->em->persist(new Branch($clan, $name, $description, $crest, $publishesNews, $approvalRequired, active: false));
            $added = true;
        }
        if (!$added) {
            return $existing;
        }
        $this->em->flush();

        return $this->branches->findByClan($clan, activeOnly: false);
    }
}
