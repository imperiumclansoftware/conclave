<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Where members report a problem or give feedback: the ticket widget of the Avenger app that
 * follows the Conclave project (menu of the account > « Signaler un problème »).
 * Not configured (AVENGER_URL empty): no menu entry.
 */
#[Route('/api/feedback', format: 'json')]
final class FeedbackController extends AbstractController
{
    public function __construct(
        #[Autowire(env: 'default::AVENGER_URL')]
        private readonly ?string $avengerUrl,
        #[Autowire(env: 'default::AVENGER_PRODUCT')]
        private readonly ?string $avengerProduct,
    ) {
    }

    #[Route('', name: 'api_feedback', methods: ['GET'])]
    public function widget(): JsonResponse
    {
        $url = rtrim(trim((string) $this->avengerUrl), '/');
        $scheme = parse_url($url, \PHP_URL_SCHEME);
        $product = trim((string) $this->avengerProduct) ?: 'conclave';

        if ('' === $url || !\in_array($scheme, ['http', 'https'], true) || null === parse_url($url, \PHP_URL_HOST)) {
            return $this->json(['widgetUrl' => null, 'product' => null]);
        }

        return $this->json(['widgetUrl' => $url.'/widget.js', 'product' => $product]);
    }
}
