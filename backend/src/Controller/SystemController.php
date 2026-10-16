<?php

namespace App\Controller;

use App\System\SystemReport;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Dashboard of the installation, for the leaders. */
#[Route('/api/admin/system', name: 'api_admin_system', methods: ['GET'], format: 'json')]
#[IsGranted('ROLE_LEADER')]
final class SystemController extends AbstractController
{
    public function __invoke(SystemReport $report): JsonResponse
    {
        return $this->json($report->build());
    }
}
