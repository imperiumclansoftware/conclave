<?php

namespace App\Controller;

use App\Api\ApiPresenter;
use App\Dto\SetupCodeRequest;
use App\Dto\SetupRequest;
use App\Entity\Clan;
use App\Entity\User;
use App\Enum\ClanRole;
use App\Repository\ClanRepository;
use App\Settings\AppSettings;
use App\Setup\SetupCode;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Installation wizard: creates the clan and its first leader, once, with the installation code
 * shown on the server (bin/console app:setup:code).
 */
#[Route('/api/setup', format: 'json')]
final class SetupController extends AbstractController
{
    public function __construct(
        private readonly ClanRepository $clans,
        private readonly SetupCode $code,
        private readonly RateLimiterFactoryInterface $setupCodeLimiter,
    ) {
    }

    /** Whether the app is installed (the clan exists); otherwise the code is ready on the server. */
    #[Route('', name: 'api_setup', methods: ['GET'])]
    public function status(): JsonResponse
    {
        $clan = $this->clans->findMain();
        if (null === $clan) {
            $this->code->current();
        }

        return $this->json(['initialized' => null !== $clan, 'clanName' => $clan?->getName()]);
    }

    /** First step of the wizard: checks the code before asking for the rest. */
    #[Route('/verify', name: 'api_setup_verify', methods: ['POST'])]
    public function verify(#[MapRequestPayload] SetupCodeRequest $request, Request $http): Response
    {
        $this->assertNotInstalled();
        $this->assertCode($request->code, $http);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[Route('', name: 'api_setup_install', methods: ['POST'])]
    public function install(
        #[MapRequestPayload] SetupRequest $request,
        Request $http,
        LockFactory $locks,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
        ValidatorInterface $validator,
        AppSettings $settings,
        JWTTokenManagerInterface $jwtManager,
        ApiPresenter $presenter,
    ): JsonResponse {
        // Two wizards submitted at the same time: only one installs
        $lock = $locks->createLock('app-setup');
        $lock->acquire(true);
        try {
            $this->assertNotInstalled();
            $this->assertCode($request->code, $http);

            $clan = new Clan(trim($request->clanName));
            $leader = new User($clan, $request->username, trim($request->displayName), ClanRole::Leader);
            $leader->setEmail($request->email);
            $leader->setPassword($hasher->hashPassword($leader, $request->password));
            UserValidation::assertValid($validator, $leader);

            $em->persist($clan);
            $em->persist($leader);
            $em->flush();
            if (null !== $request->appUrl && '' !== trim($request->appUrl)) {
                $settings->set(AppSettings::APP_URL, rtrim(trim($request->appUrl), '/').'/');
            }
            $this->code->clear();
        } finally {
            $lock->release();
        }

        return $this->json([
            'token' => $jwtManager->create($leader),
            'user' => $presenter->me($leader),
        ], Response::HTTP_CREATED);
    }

    private function assertNotInstalled(): void
    {
        if (null !== $this->clans->findMain()) {
            throw new ConflictHttpException('L\'application est déjà installée.');
        }
    }

    /** A few attempts per address: the code cannot be guessed by trying. */
    private function assertCode(string $code, Request $http): void
    {
        $limit = $this->setupCodeLimiter->create($http->getClientIp() ?? 'unknown')->consume();
        if (!$limit->isAccepted()) {
            throw new TooManyRequestsHttpException($limit->getRetryAfter()->getTimestamp() - time(), 'Trop de tentatives : réessayez dans quelques minutes.');
        }
        if (!$this->code->matches($code)) {
            throw new AccessDeniedHttpException('Code d\'installation incorrect. Il est donné sur le serveur par « bin/console app:setup:code ».');
        }
    }
}
