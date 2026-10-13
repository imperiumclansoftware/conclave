<?php

namespace App\Controller;

use App\Chat\RealtimeNotifier;
use App\Dto\BranchLinkRequest;
use App\Entity\Branch;
use App\Entity\BranchLink;
use App\Entity\User;
use App\Repository\BranchLinkRepository;
use App\Repository\BranchRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Favourite links of the branches, shown to their members in the links sidebar. Members add them;
 * the member who added a link, or a leader, removes it.
 */
#[Route('/api', format: 'json')]
final class BranchLinkController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RealtimeNotifier $notifier,
    ) {
    }

    /** Links of my active branches, grouped by branch (every branch of mine, even without links). */
    #[Route('/me/branch-links', name: 'api_my_branch_links', methods: ['GET'])]
    public function mine(#[CurrentUser] User $me, BranchRepository $branches, BranchLinkRepository $links): JsonResponse
    {
        $groups = [];
        foreach ($branches->findForMember($me) as $branch) {
            $groups[$branch->getId()] = [
                'branch' => ['id' => $branch->getId(), 'name' => $branch->getName(), 'crest' => $branch->getCrest()?->value],
                'links' => [],
            ];
        }
        foreach ($links->forMember($me) as $link) {
            $groups[$link->getBranch()->getId()]['links'][] = $this->present($link, $me);
        }

        return $this->json(array_values($groups));
    }

    #[Route('/clan/branches/{id}/links', name: 'api_branch_link_add', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function add(Branch $branch, #[MapRequestPayload] BranchLinkRequest $request, #[CurrentUser] User $me): JsonResponse
    {
        if ($branch->getClan()->getId() !== $me->getClan()->getId() || !$branch->isActive()) {
            throw new NotFoundHttpException('Branche introuvable.');
        }
        if (!$branch->hasMember($me)) {
            throw new AccessDeniedHttpException('Seuls les membres de la branche ajoutent ses liens.');
        }

        $link = new BranchLink($branch, $me, $request->cleanTitle(), $request->cleanUrl());
        $this->em->persist($link);
        $this->em->flush();
        $this->changed($branch);

        return $this->json($this->present($link, $me), Response::HTTP_CREATED);
    }

    #[Route('/branch-links/{id}', name: 'api_branch_link_remove', requirements: ['id' => Requirement::DIGITS], methods: ['DELETE'])]
    public function remove(BranchLink $link, #[CurrentUser] User $me): Response
    {
        $branch = $link->getBranch();
        if ($branch->getClan()->getId() !== $me->getClan()->getId()) {
            throw new NotFoundHttpException('Lien introuvable.');
        }
        if (!$this->canRemove($link, $me)) {
            throw new AccessDeniedHttpException('Seul le membre qui a ajouté ce lien, ou un dirigeant, peut le retirer.');
        }

        $this->em->remove($link);
        $this->em->flush();
        $this->changed($branch);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    private function canRemove(BranchLink $link, User $me): bool
    {
        return $me->isLeader() || $link->getAddedBy() === $me;
    }

    /** The other members refresh their sidebar */
    private function changed(Branch $branch): void
    {
        foreach ($branch->getMembers() as $member) {
            $this->notifier->branchLinksChanged($member);
        }
    }

    /** @return array{id: ?int, title: string, url: string, host: ?string, addedBy: ?array{id: ?int, displayName: string}, canRemove: bool} */
    private function present(BranchLink $link, User $me): array
    {
        $author = $link->getAddedBy();

        return [
            'id' => $link->getId(),
            'title' => $link->getTitle(),
            'url' => $link->getUrl(),
            'host' => preg_replace('/^www\./', '', (string) parse_url($link->getUrl(), \PHP_URL_HOST)),
            'addedBy' => null !== $author && $author->isActive() ? ['id' => $author->getId(), 'displayName' => $author->getDisplayName()] : null,
            'canRemove' => $this->canRemove($link, $me),
        ];
    }
}
