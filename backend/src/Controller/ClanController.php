<?php

namespace App\Controller;

use App\Api\ApiPresenter;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/clan', format: 'json')]
final class ClanController extends AbstractController
{
    public function __construct(
        private readonly ApiPresenter $presenter,
    ) {
    }

    #[Route('/members', name: 'api_clan_members', methods: ['GET'])]
    public function members(#[CurrentUser] User $user): JsonResponse
    {
        return $this->json(array_map(
            $this->presenter->user(...),
            $user->getClan()->getMembers()->getValues(),
        ));
    }
}
