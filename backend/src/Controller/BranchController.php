<?php

namespace App\Controller;

use App\Api\ApiPresenter;
use App\Chat\RealtimeNotifier;
use App\Clan\BranchCatalog;
use App\Clan\MemberManager;
use App\Dto\BranchActivationRequest;
use App\Dto\BranchRequest;
use App\Entity\Branch;
use App\Entity\BranchRequest as MembershipRequest;
use App\Entity\User;
use App\Repository\BranchRepository;
use App\Repository\BranchRequestRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Branches of the clan, from the app's catalogue: activated by the leaders, joined freely by the
 * members, or on a leader's approval for some branches (e.g. the Communicants, who publish the news).
 */
#[Route('/api', format: 'json')]
final class BranchController extends AbstractController
{
    public function __construct(
        private readonly ApiPresenter $presenter,
        private readonly BranchRepository $branches,
        private readonly EntityManagerInterface $em,
        private readonly BranchRequestRepository $requests,
        private readonly MemberManager $members,
        private readonly RealtimeNotifier $notifier,
    ) {
    }

    #[Route('/clan/branches', name: 'api_branches', methods: ['GET'])]
    public function list(#[CurrentUser] User $me): JsonResponse
    {
        return $this->json(array_map($this->presenter->branch(...), $this->branches->findByClan($me->getClan())));
    }

    /** Joins the branch, or asks a leader to approve it (leaders themselves join directly). */
    #[Route('/clan/branches/{id}/membership', name: 'api_branch_join', requirements: ['id' => Requirement::DIGITS], methods: ['PUT'])]
    public function join(Branch $branch, #[CurrentUser] User $me): JsonResponse
    {
        $this->sameClan($branch, $me);
        if (!$branch->isActive()) {
            throw new NotFoundHttpException('Cette branche n\'est pas active.');
        }
        if ($branch->hasMember($me)) {
            return $this->json($this->presenter->branch($branch));
        }

        if ($branch->isApprovalRequired() && !$me->isLeader()) {
            if (null === $this->requests->findOneFor($branch, $me)) {
                $request = new MembershipRequest($branch, $me);
                $branch->getRequests()->add($request);
                $this->em->persist($request);
                $this->em->flush();
                $this->notifier->signupsChanged($this->members->leaders($me->getClan()));
            }

            return $this->json($this->presenter->branch($branch), Response::HTTP_ACCEPTED);
        }

        $branch->join($me);
        $this->em->flush();

        return $this->json($this->presenter->branch($branch));
    }

    /** Leaves the branch, or cancels the request to join it. */
    #[Route('/clan/branches/{id}/membership', name: 'api_branch_leave', requirements: ['id' => Requirement::DIGITS], methods: ['DELETE'])]
    public function leave(Branch $branch, #[CurrentUser] User $me): JsonResponse
    {
        $this->sameClan($branch, $me);
        $branch->leave($me);
        if (null !== $request = $this->requests->findOneFor($branch, $me)) {
            $this->removeRequest($request);
        }
        $this->em->flush();

        return $this->json($this->presenter->branch($branch));
    }

    #[Route('/admin/branch-requests', name: 'api_admin_branch_requests', methods: ['GET'])]
    #[IsGranted('ROLE_LEADER')]
    public function pendingRequests(#[CurrentUser] User $me): JsonResponse
    {
        return $this->json(array_map($this->presenter->branchRequest(...), $this->requests->pending($me->getClan())));
    }

    #[Route('/admin/branch-requests/{id}/approve', name: 'api_admin_branch_request_approve', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    #[IsGranted('ROLE_LEADER')]
    public function approve(MembershipRequest $request, #[CurrentUser] User $me): JsonResponse
    {
        $branch = $request->getBranch();
        $this->sameClan($branch, $me);
        $branch->join($request->getUser());
        $this->decided($request);

        return $this->json($this->presenter->branch($branch));
    }

    #[Route('/admin/branch-requests/{id}/reject', name: 'api_admin_branch_request_reject', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    #[IsGranted('ROLE_LEADER')]
    public function reject(MembershipRequest $request, #[CurrentUser] User $me): JsonResponse
    {
        $branch = $request->getBranch();
        $this->sameClan($branch, $me);
        $this->decided($request);

        return $this->json($this->presenter->branch($branch));
    }

    /** Every branch of the catalogue, active or not. */
    #[Route('/admin/branches', name: 'api_admin_branches', methods: ['GET'])]
    #[IsGranted('ROLE_LEADER')]
    public function all(#[CurrentUser] User $me, BranchCatalog $catalog): JsonResponse
    {
        return $this->json(array_map($this->presenter->branch(...), $catalog->sync($me->getClan())));
    }

    /** Deactivating hides the branch; its members keep their membership for its reactivation. */
    #[Route('/admin/branches/{id}/active', name: 'api_admin_branch_activation', requirements: ['id' => Requirement::DIGITS], methods: ['PUT'])]
    #[IsGranted('ROLE_LEADER')]
    public function activation(Branch $branch, #[MapRequestPayload] BranchActivationRequest $request, #[CurrentUser] User $me): JsonResponse
    {
        $this->sameClan($branch, $me);
        if ($branch->isActive() !== $request->active) {
            $branch->setActive($request->active);
            $this->em->flush();
            // Their rights may change (publishing the news), and leaders' pending requests too
            foreach ($branch->getMembers() as $member) {
                $this->notifier->branchesChanged($member);
            }
            if (!$branch->getRequests()->isEmpty()) {
                $this->notifier->signupsChanged($this->members->leaders($me->getClan()));
            }
        }

        return $this->json($this->presenter->branch($branch));
    }

    #[Route('/admin/branches/{id}', name: 'api_admin_branch_update', requirements: ['id' => Requirement::DIGITS], methods: ['PUT'])]
    #[IsGranted('ROLE_LEADER')]
    public function update(Branch $branch, #[MapRequestPayload] BranchRequest $request, #[CurrentUser] User $me): JsonResponse
    {
        $this->sameClan($branch, $me);
        $this->assertNameAvailable($me, $request->cleanName(), $branch);
        $branch->update($request->cleanName(), $request->cleanDescription(), $request->publishesNews, $request->approvalRequired);
        $this->em->flush();

        return $this->json($this->presenter->branch($branch));
    }

    /** The request is answered: the member and the leaders are told. */
    private function decided(MembershipRequest $request): void
    {
        $user = $request->getUser();
        $this->removeRequest($request);
        $this->em->flush();
        $this->notifier->branchesChanged($user);
        $this->notifier->signupsChanged($this->members->leaders($user->getClan()));
    }

    private function removeRequest(MembershipRequest $request): void
    {
        $request->getBranch()->getRequests()->removeElement($request);
        $this->em->remove($request);
    }

    private function sameClan(Branch $branch, User $me): void
    {
        if ($branch->getClan()->getId() !== $me->getClan()->getId()) {
            throw new NotFoundHttpException('Branche introuvable.');
        }
    }

    private function assertNameAvailable(User $me, string $name, ?Branch $except = null): void
    {
        if (null !== $this->branches->findOneByName($me->getClan(), $name, $except)) {
            throw new ConflictHttpException(\sprintf('La branche « %s » existe déjà.', $name));
        }
    }
}
