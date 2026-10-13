<?php

namespace App\Controller;

use App\Chat\UserTopic;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mercure\Authorization;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class MercureController extends AbstractController
{
    /**
     * Sets the HttpOnly cookie allowing the browser to subscribe to the user's private topic only,
     * and tells the client where to subscribe.
     */
    #[Route('/api/mercure/authorize', name: 'api_mercure_authorize', methods: ['POST'], format: 'json')]
    public function authorize(
        #[CurrentUser] User $user,
        Request $request,
        Authorization $authorization,
        HubInterface $hub,
        UserTopic $userTopic,
    ): JsonResponse {
        $topic = $userTopic->for($user);

        $response = $this->json(['hubUrl' => $hub->getPublicUrl(), 'topic' => $topic]);
        $response->headers->setCookie($authorization->createCookie($request, [$topic]));

        return $response;
    }
}
