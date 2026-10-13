<?php

namespace App\Controller;

use App\Discord\DiscordApi;
use App\Discord\DiscordApiException;
use App\Discord\DiscordConfig;
use App\Discord\DiscordLinker;
use App\Discord\DiscordOAuth;
use App\Dto\DiscordAuthorizeRequest;
use App\Dto\DiscordCallbackRequest;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/discord', format: 'json')]
final class DiscordController extends AbstractController
{
    public function __construct(
        private readonly DiscordConfig $config,
    ) {
    }

    /**
     * Discord integration status for the current user and their clan.
     */
    #[Route('', name: 'api_discord_status', methods: ['GET'])]
    public function status(#[CurrentUser] User $user, DiscordApi $api): JsonResponse
    {
        $guildId = $user->getClan()->getDiscordGuildId();
        $onServer = null;
        if ($this->config->isEnabled() && $guildId && $user->getDiscordId()) {
            try {
                $onServer = $api->isGuildMember($guildId, $user->getDiscordId());
            } catch (DiscordApiException) {
                // Unknown
            }
        }

        return $this->json([
            'enabled' => $this->config->isEnabled(),
            'account' => $user->getDiscordId() ? [
                'id' => $user->getDiscordId(),
                'username' => $user->getDiscordUsername(),
                'avatarUrl' => $user->getDiscordAvatarUrl(),
                'onServer' => $onServer,
            ] : null,
            'guild' => $guildId ? ['id' => $guildId, 'name' => $user->getClan()->getDiscordGuildName()] : null,
        ]);
    }

    /**
     * Starts an OAuth2 flow: linking the user's account, or (leaders) adding the bot to the clan's server.
     */
    #[Route('/authorize', name: 'api_discord_authorize', methods: ['POST'])]
    public function authorize(#[CurrentUser] User $user, #[MapRequestPayload] DiscordAuthorizeRequest $request, DiscordOAuth $oauth): JsonResponse
    {
        $this->ensureEnabled();
        if (DiscordOAuth::INSTALL === $request->purpose && !$user->isLeader()) {
            throw new AccessDeniedHttpException('Seul un dirigeant peut connecter le serveur Discord du clan.');
        }

        return $this->json(['url' => $oauth->authorizeUrl($user, $request->purpose)]);
    }

    /**
     * Completes the OAuth2 flow with the code Discord sent back to the frontend.
     */
    #[Route('/callback', name: 'api_discord_callback', methods: ['POST'])]
    public function callback(
        #[CurrentUser] User $user,
        #[MapRequestPayload] DiscordCallbackRequest $request,
        DiscordOAuth $oauth,
        DiscordLinker $linker,
    ): JsonResponse {
        $this->ensureEnabled();
        $purpose = $oauth->consumeState($user, $request->state)
            ?? throw new BadRequestHttpException('Demande d\'autorisation expirée ou invalide, recommencez.');

        try {
            $linker->complete($user, $purpose, $request->code);
        } catch (\DomainException $e) {
            throw new ConflictHttpException($e->getMessage(), $e);
        } catch (DiscordApiException $e) {
            throw new ServiceUnavailableHttpException(null, 'Discord n\'a pas validé la demande, recommencez.', $e);
        }

        return $this->json(['purpose' => $purpose]);
    }

    #[Route('/account', name: 'api_discord_unlink', methods: ['DELETE'])]
    public function unlink(#[CurrentUser] User $user, DiscordLinker $linker): Response
    {
        $linker->unlink($user);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[Route('/guild', name: 'api_discord_disconnect_guild', methods: ['DELETE'])]
    #[IsGranted('ROLE_LEADER')]
    public function disconnectGuild(#[CurrentUser] User $user, DiscordLinker $linker): Response
    {
        $linker->disconnectGuild($user);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    private function ensureEnabled(): void
    {
        if (!$this->config->isEnabled()) {
            throw new NotFoundHttpException('L\'intégration Discord n\'est pas configurée.');
        }
    }
}
