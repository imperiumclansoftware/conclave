<?php

namespace App\Controller;

use App\Api\ApiPresenter;
use App\Clan\MemberManager;
use App\Dto\ChangeRoleRequest;
use App\Dto\CreateMemberRequest;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Administration of the clan's members (leaders): sign-up requests, roles, removal.
 */
#[Route('/api/admin', format: 'json')]
#[IsGranted('ROLE_LEADER')]
final class AdminMembersController extends AbstractController
{
    public function __construct(
        private readonly ApiPresenter $presenter,
        private readonly MemberManager $members,
    ) {
    }

    #[Route('/signups', name: 'api_admin_signups', methods: ['GET'])]
    public function signups(#[CurrentUser] User $me): JsonResponse
    {
        return $this->json(array_map($this->account(...), $this->members->pending($me->getClan())));
    }

    #[Route('/signups/{id}/approve', name: 'api_admin_signup_approve', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function approve(User $user, #[CurrentUser] User $me): JsonResponse
    {
        $this->sameClan($user, $me);
        $this->members->approve($user);

        return $this->json($this->account($user));
    }

    #[Route('/signups/{id}/reject', name: 'api_admin_signup_reject', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function reject(User $user, #[CurrentUser] User $me): Response
    {
        $this->sameClan($user, $me);
        try {
            $this->members->reject($user);
        } catch (\DomainException $e) {
            throw new ConflictHttpException($e->getMessage(), $e);
        }

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[Route('/members', name: 'api_admin_members', methods: ['GET'])]
    public function list(#[CurrentUser] User $me): JsonResponse
    {
        return $this->json(array_map($this->account(...), $me->getClan()->getMembers()->getValues()));
    }

    /** Adds a member directly (active right away). */
    #[Route('/members', name: 'api_admin_members_create', methods: ['POST'])]
    public function create(
        #[CurrentUser] User $leader,
        #[MapRequestPayload] CreateMemberRequest $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
        ValidatorInterface $validator,
    ): JsonResponse {
        $member = new User($leader->getClan(), $request->username, $request->displayName, $request->role);
        $member->setEmail($request->email);
        $member->setPassword($hasher->hashPassword($member, $request->password));
        UserValidation::assertValid($validator, $member);

        $em->persist($member);
        $em->flush();
        $this->members->added($member);

        return $this->json($this->account($member), Response::HTTP_CREATED);
    }

    #[Route('/members/{id}/role', name: 'api_admin_member_role', requirements: ['id' => Requirement::DIGITS], methods: ['PUT'])]
    public function role(User $user, #[CurrentUser] User $me, #[MapRequestPayload] ChangeRoleRequest $request): JsonResponse
    {
        $this->sameClan($user, $me);
        try {
            $this->members->changeRole($user, $request->role);
        } catch (\DomainException $e) {
            throw new ConflictHttpException($e->getMessage(), $e);
        }

        return $this->json($this->account($user));
    }

    #[Route('/members/{id}', name: 'api_admin_member_remove', requirements: ['id' => Requirement::DIGITS], methods: ['DELETE'])]
    public function remove(User $user, #[CurrentUser] User $me): Response
    {
        $this->sameClan($user, $me);
        try {
            $this->members->remove($user, $me);
        } catch (\DomainException $e) {
            throw new ConflictHttpException($e->getMessage(), $e);
        }

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    /**
     * Account details visible to leaders (e-mail included).
     *
     * @return array{id: ?int, username: string, displayName: string, role: string, avatarUrl: ?string, email: ?string, status: string, createdAt: string}
     */
    private function account(User $user): array
    {
        return $this->presenter->user($user) + [
            'email' => $user->getEmail(),
            'status' => $user->getStatus()->value,
            'createdAt' => $user->getCreatedAt()->format(\DATE_ATOM),
        ];
    }

    private function sameClan(User $user, User $me): void
    {
        if ($user->getClan()->getId() !== $me->getClan()->getId()) {
            throw new NotFoundHttpException();
        }
    }
}
