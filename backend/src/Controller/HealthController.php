<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class HealthController extends AbstractController
{
    #[Route('/api/health', name: 'api_health', methods: ['GET'], format: 'json')]
    public function __invoke(): JsonResponse
    {
        return $this->json([
            'status' => 'ok',
            'timestamp' => (new \DateTimeImmutable())->format(\DATE_ATOM),
        ]);
    }
}
