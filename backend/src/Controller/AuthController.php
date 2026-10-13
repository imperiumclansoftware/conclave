<?php

namespace App\Controller;

use App\Api\ApiPresenter;
use App\Chat\RealtimeNotifier;
use App\Dto\ForgotPasswordRequest;
use App\Dto\RegisterRequest;
use App\Dto\ResetPasswordRequest;
use App\Entity\Clan;
use App\Entity\User;
use App\Enum\ClanRole;
use App\Mail\AppMailer;
use App\Repository\ClanRepository;
use App\Repository\UserRepository;
use App\Security\PasswordResetTokens;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api', format: 'json')]
final class AuthController extends AbstractController
{
    public function __construct(
        private readonly ApiPresenter $presenter,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Signing up creates a pending account that a leader must approve. The clan and its first
     * leader come from the installation wizard (SetupController).
     */
    #[Route('/register', name: 'api_register', methods: ['POST'])]
    public function register(
        #[MapRequestPayload] RegisterRequest $request,
        ClanRepository $clans,
        UserPasswordHasherInterface $hasher,
        ValidatorInterface $validator,
        AppMailer $mailer,
        RealtimeNotifier $notifier,
    ): JsonResponse {
        $clan = $clans->findMain()
            ?? throw new ConflictHttpException('L\'application n\'est pas encore installée : un dirigeant doit d\'abord passer par l\'assistant d\'installation.');

        $user = new User($clan, $request->username, trim($request->displayName), ClanRole::Member);
        $user->setEmail($request->email);
        $user->setPassword($hasher->hashPassword($user, $request->password));
        $user->markPending();
        UserValidation::assertValid($validator, $user);

        $this->em->persist($user);
        $this->em->flush();

        $leaders = $this->leadersOf($clan);
        $mailer->signupRequested($user, $leaders);
        $notifier->signupsChanged($leaders);

        return $this->json(['status' => 'pending'], Response::HTTP_ACCEPTED);
    }

    #[Route('/me', name: 'api_me', methods: ['GET'])]
    public function me(#[CurrentUser] User $user): JsonResponse
    {
        return $this->json($this->presenter->me($user));
    }

    /**
     * Sends a password reset link. Always answers the same way, so that it does not tell
     * whether an account exists.
     */
    #[Route('/password/forgot', name: 'api_password_forgot', methods: ['POST'])]
    public function forgotPassword(
        #[MapRequestPayload] ForgotPasswordRequest $request,
        UserRepository $users,
        PasswordResetTokens $tokens,
        AppMailer $mailer,
    ): Response {
        $identifier = mb_strtolower(trim($request->identifier));
        $user = $users->findOneBy(['username' => $identifier]) ?? $users->findOneBy(['email' => $identifier]);
        if (null !== $user && $user->isActive()) {
            $mailer->passwordReset($user, $tokens->create($user), PasswordResetTokens::VALID_MINUTES);
        }

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[Route('/password/reset', name: 'api_password_reset', methods: ['POST'])]
    public function resetPassword(
        #[MapRequestPayload] ResetPasswordRequest $request,
        PasswordResetTokens $tokens,
        UserPasswordHasherInterface $hasher,
    ): Response {
        $user = $tokens->verify($request->token)
            ?? throw new UnprocessableEntityHttpException('Ce lien de réinitialisation est invalide ou a expiré. Faites une nouvelle demande.');

        $user->setPassword($hasher->hashPassword($user, $request->password));
        $this->em->flush();

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    /** @return list<User> */
    private function leadersOf(Clan $clan): array
    {
        return $clan->getMembers()->filter(static fn (User $u): bool => $u->isLeader())->getValues();
    }
}
