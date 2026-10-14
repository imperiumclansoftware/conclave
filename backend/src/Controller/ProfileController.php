<?php

namespace App\Controller;

use App\Api\ApiPresenter;
use App\Chat\RealtimeNotifier;
use App\Dto\AvatarSourceRequest;
use App\Dto\ChangePasswordRequest;
use App\Dto\FactionRequest;
use App\Dto\GravatarRequest;
use App\Dto\UpdateProfileRequest;
use App\Entity\Branch;
use App\Entity\User;
use App\Enum\AvatarSource;
use App\Enum\GamerTag;
use App\Games\GamesAccess;
use App\Profile\Avatars;
use App\Profile\AvatarStorage;
use App\Profile\Gravatar;
use App\Repository\BranchRepository;
use App\Repository\NewsRepository;
use App\Repository\UserGameRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Attribute\MapUploadedFile;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api', format: 'json')]
final class ProfileController extends AbstractController
{
    public function __construct(
        private readonly ApiPresenter $presenter,
        private readonly EntityManagerInterface $em,
        private readonly RealtimeNotifier $notifier,
    ) {
    }

    #[Route('/me/profile', name: 'api_my_profile', methods: ['GET'])]
    public function mine(#[CurrentUser] User $user): JsonResponse
    {
        return $this->json($this->presenter->profile($user) + [
            'email' => $user->getEmail(),
            'networks' => self::networks(),
            'avatar' => $this->presenter->avatarSettings($user),
        ]);
    }

    #[Route('/me/profile', name: 'api_my_profile_update', methods: ['PUT'])]
    public function update(#[CurrentUser] User $user, #[MapRequestPayload] UpdateProfileRequest $request, ValidatorInterface $validator): JsonResponse
    {
        $user->setDisplayName(trim($request->displayName));
        $user->setBio(trim((string) $request->bio) ?: null);
        $user->setGamerTags($request->cleanGamerTags());
        $user->setEmail($request->email);
        UserValidation::assertValid($validator, $user);
        $this->em->flush();
        $this->notifier->memberUpdated($user);

        return $this->json($this->presenter->profile($user) + [
            'email' => $user->getEmail(),
            'networks' => self::networks(),
            'avatar' => $this->presenter->avatarSettings($user),
        ]);
    }

    #[Route('/me/avatar', name: 'api_my_avatar_upload', methods: ['POST'])]
    public function uploadAvatar(
        #[CurrentUser] User $user,
        #[MapUploadedFile([
            new Assert\NotNull(message: 'Choisissez une image.'),
            new Assert\Image(
                maxSize: '5M',
                mimeTypes: ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
                minWidth: 64,
                minHeight: 64,
                maxWidth: 6000,
                maxHeight: 6000,
                mimeTypesMessage: 'Formats acceptés : JPEG, PNG, WebP ou GIF.',
            ),
        ])]
        UploadedFile $avatar,
        AvatarStorage $storage,
    ): JsonResponse {
        try {
            $storage->store($user, $avatar);
        } catch (\InvalidArgumentException $e) {
            throw new UnprocessableEntityHttpException($e->getMessage(), $e);
        }
        // A freshly uploaded picture is the one the member wants to show
        $user->setAvatarSource(AvatarSource::Upload);

        return $this->avatarChanged($user);
    }

    #[Route('/me/avatar', name: 'api_my_avatar_remove', methods: ['DELETE'])]
    public function removeAvatar(#[CurrentUser] User $user, AvatarStorage $storage): JsonResponse
    {
        $storage->remove($user);
        if (AvatarSource::Upload === $user->getAvatarSource()) {
            $user->setAvatarSource(null);
        }

        return $this->avatarChanged($user);
    }

    /**
     * Uses the Gravatar of an e-mail address. Only its SHA-256 is kept, not the address.
     */
    #[Route('/me/avatar/gravatar', name: 'api_my_gravatar', methods: ['PUT'])]
    public function useGravatar(#[CurrentUser] User $user, #[MapRequestPayload] GravatarRequest $request, Gravatar $gravatar): JsonResponse
    {
        $hash = Gravatar::hash($request->email);

        match ($gravatar->exists($hash)) {
            true => null,
            false => throw new UnprocessableEntityHttpException('Aucun Gravatar n\'est associé à cette adresse.'),
            null => throw new ServiceUnavailableHttpException(null, 'Gravatar est injoignable, réessayez plus tard.'),
        };

        $user->setGravatarHash($hash);
        $user->setAvatarSource(AvatarSource::Gravatar);

        return $this->avatarChanged($user);
    }

    #[Route('/me/avatar/gravatar', name: 'api_my_gravatar_remove', methods: ['DELETE'])]
    public function removeGravatar(#[CurrentUser] User $user): JsonResponse
    {
        $user->setGravatarHash(null);
        if (AvatarSource::Gravatar === $user->getAvatarSource()) {
            $user->setAvatarSource(null);
        }

        return $this->avatarChanged($user);
    }

    /** Chooses which of the configured avatars to show (or the initial). */
    #[Route('/me/avatar/source', name: 'api_my_avatar_source', methods: ['PUT'])]
    public function chooseSource(#[CurrentUser] User $user, #[MapRequestPayload] AvatarSourceRequest $request): JsonResponse
    {
        if (AvatarSource::Initials !== $request->source && !isset(Avatars::available($user)[$request->source->value])) {
            throw new UnprocessableEntityHttpException('Cette source d\'avatar n\'est pas configurée.');
        }
        $user->setAvatarSource($request->source);

        return $this->avatarChanged($user);
    }

    private function avatarChanged(User $user): JsonResponse
    {
        $this->em->flush();
        $this->notifier->memberUpdated($user);

        return $this->json($this->presenter->me($user) + ['avatar' => $this->presenter->avatarSettings($user)]);
    }

    /** Theme of the interface, applied on every device of the member. */
    #[Route('/me/faction', name: 'api_my_faction', methods: ['PUT'])]
    public function faction(#[CurrentUser] User $user, #[MapRequestPayload] FactionRequest $request): JsonResponse
    {
        $user->setFaction($request->faction);
        $this->em->flush();

        return $this->json($this->presenter->me($user));
    }

    #[Route('/me/password', name: 'api_my_password', methods: ['PUT'])]
    public function changePassword(
        #[CurrentUser] User $user,
        #[MapRequestPayload] ChangePasswordRequest $request,
        UserPasswordHasherInterface $hasher,
    ): Response {
        if (!$hasher->isPasswordValid($user, $request->currentPassword)) {
            throw new UnprocessableEntityHttpException('Mot de passe actuel incorrect.');
        }
        $user->setPassword($hasher->hashPassword($user, $request->newPassword));
        $this->em->flush();

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    /**
     * Public profile of a clan member, with the games they play.
     */
    #[Route('/members/{id}', name: 'api_member_profile', requirements: ['id' => Requirement::DIGITS], methods: ['GET'])]
    public function member(User $member, #[CurrentUser] User $user, UserGameRepository $userGames, GamesAccess $games): JsonResponse
    {
        if ($member->getClan()->getId() !== $user->getClan()->getId() || !$member->isActive()) {
            throw new NotFoundHttpException('Membre introuvable.');
        }

        return $this->json($this->presenter->profile($member) + [
            'networks' => self::networks(),
            // The games part belongs to the Joueurs branch: null for the other members
            'games' => $games->allowed($user) ? array_map($this->presenter->userGame(...), $userGames->findForUser($member)) : null,
        ]);
    }

    /**
     * Summary shown when hovering a member's name: branches and activity in the clan.
     */
    #[Route('/members/{id}/card', name: 'api_member_card', requirements: ['id' => Requirement::DIGITS], methods: ['GET'])]
    public function card(User $member, #[CurrentUser] User $user, UserGameRepository $userGames, NewsRepository $news, BranchRepository $branches, GamesAccess $games): JsonResponse
    {
        if ($member->getClan()->getId() !== $user->getClan()->getId() || !$member->isActive()) {
            throw new NotFoundHttpException('Membre introuvable.');
        }

        $memberBranches = $branches->findForMember($member);

        return $this->json($this->presenter->user($member) + [
            'memberSince' => $member->getCreatedAt()->format(\DATE_ATOM),
            'discordUsername' => $member->getDiscordUsername(),
            'branches' => array_map(
                static fn (Branch $branch): array => ['id' => $branch->getId(), 'name' => $branch->getName(), 'crest' => $branch->getCrest()?->value],
                $memberBranches,
            ),
            'stats' => [
                'games' => $games->allowed($user) ? $userGames->countForUser($member) : null,
                'news' => $news->countByAuthor($member),
                'branches' => \count($memberBranches),
            ],
        ]);
    }

    /** Avatars are public, under unguessable names (see AvatarStorage). */
    #[Route('/avatars/{name}', name: 'api_avatar', requirements: ['name' => AvatarStorage::NAME_PATTERN], methods: ['GET'], format: 'html')]
    public function avatar(string $name, AvatarStorage $storage): BinaryFileResponse
    {
        $path = $storage->path($name);
        if (!is_file($path)) {
            throw new NotFoundHttpException();
        }

        $response = new BinaryFileResponse($path, headers: ['Content-Type' => 'image/webp']);
        $response->setPublic();
        $response->setMaxAge(31536000);
        $response->setImmutable();

        return $response;
    }

    /** @return list<array{value: string, label: string}> */
    private static function networks(): array
    {
        return array_map(static fn (GamerTag $t): array => ['value' => $t->value, 'label' => $t->label()], GamerTag::cases());
    }
}
