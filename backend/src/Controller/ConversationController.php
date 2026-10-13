<?php

namespace App\Controller;

use App\Api\ApiPresenter;
use App\Chat\ChatService;
use App\Dto\MarkReadRequest;
use App\Dto\OpenConversationRequest;
use App\Dto\SendMessageRequest;
use App\Entity\Conversation;
use App\Entity\User;
use App\Repository\ConversationRepository;
use App\Repository\MessageRepository;
use App\Repository\UserRepository;
use App\Security\ConversationVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/conversations', format: 'json')]
final class ConversationController extends AbstractController
{
    private const PAGE_SIZE = 50;

    public function __construct(
        private readonly ApiPresenter $presenter,
        private readonly ChatService $chat,
    ) {
    }

    /**
     * Lists the user's conversations with their unread counts, so that messages
     * received while offline are visible as soon as the user comes back.
     */
    #[Route('', name: 'api_conversations', methods: ['GET'])]
    public function list(#[CurrentUser] User $user, ConversationRepository $conversations): JsonResponse
    {
        $unread = $conversations->countUnreadByConversation($user);

        return $this->json(array_map(
            fn (Conversation $c): array => $this->presenter->conversation($c, $user, $unread[$c->getId()] ?? 0),
            $conversations->findForUser($user),
        ));
    }

    /**
     * Opens (or creates) the one-to-one conversation with another clan member.
     */
    #[Route('', name: 'api_conversations_open', methods: ['POST'])]
    public function open(
        #[CurrentUser] User $user,
        #[MapRequestPayload] OpenConversationRequest $request,
        UserRepository $users,
        ConversationRepository $conversations,
    ): JsonResponse {
        $other = $users->find($request->userId) ?? throw $this->createNotFoundException('Membre introuvable.');

        try {
            $conversation = $this->chat->openDirect($user, $other);
        } catch (\InvalidArgumentException $e) {
            throw new UnprocessableEntityHttpException($e->getMessage(), $e);
        }

        $unread = $conversations->countUnreadByConversation($user)[$conversation->getId()] ?? 0;

        return $this->json($this->presenter->conversation($conversation, $user, $unread));
    }

    #[Route('/{id}/messages', name: 'api_conversation_messages', requirements: ['id' => Requirement::DIGITS], methods: ['GET'])]
    #[IsGranted(ConversationVoter::ACCESS, 'conversation')]
    public function messages(
        Conversation $conversation,
        MessageRepository $messages,
        #[MapQueryParameter] ?int $before = null,
    ): JsonResponse {
        return $this->json(array_map(
            $this->presenter->message(...),
            $messages->findPage($conversation, $before, self::PAGE_SIZE),
        ));
    }

    #[Route('/{id}/messages', name: 'api_conversation_send', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    #[IsGranted(ConversationVoter::ACCESS, 'conversation')]
    public function send(
        Conversation $conversation,
        #[CurrentUser] User $user,
        #[MapRequestPayload] SendMessageRequest $request,
    ): JsonResponse {
        $message = $this->chat->send($conversation, $user, $request->content);

        return $this->json($this->presenter->message($message), Response::HTTP_CREATED);
    }

    #[Route('/{id}/read', name: 'api_conversation_read', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    #[IsGranted(ConversationVoter::ACCESS, 'conversation')]
    public function read(
        Conversation $conversation,
        #[CurrentUser] User $user,
        #[MapRequestPayload] MarkReadRequest $request,
    ): Response {
        $this->chat->markRead($conversation, $user, $request->messageId);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
