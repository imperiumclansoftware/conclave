<?php

namespace App\Controller;

use App\Api\ApiPresenter;
use App\Dto\NewsRequest;
use App\Entity\News;
use App\Entity\User;
use App\News\NewsImageStorage;
use App\Repository\NewsRepository;
use App\Security\NewsVoter;
use App\Validator\NewsImage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Attribute\MapUploadedFile;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * News of the clan: read by every member, written by the leaders and the publishing branches.
 */
#[Route('/api', format: 'json')]
final class NewsController extends AbstractController
{
    private const PAGE_SIZE = 12;

    public function __construct(
        private readonly ApiPresenter $presenter,
        private readonly EntityManagerInterface $em,
        private readonly NewsImageStorage $images,
    ) {
    }

    /** Most recent first; ?before={id} for the next page. */
    #[Route('/news', name: 'api_news', methods: ['GET'])]
    public function list(#[CurrentUser] User $me, NewsRepository $news, #[MapQueryParameter] ?int $before = null): JsonResponse
    {
        $page = $news->page($me->getClan(), $before, self::PAGE_SIZE + 1);

        return $this->json([
            'items' => array_map($this->present(...), \array_slice($page, 0, self::PAGE_SIZE)),
            'hasMore' => \count($page) > self::PAGE_SIZE,
        ]);
    }

    #[Route('/news/{id}', name: 'api_news_show', requirements: ['id' => Requirement::DIGITS], methods: ['GET'])]
    public function show(News $news, #[CurrentUser] User $me): JsonResponse
    {
        $this->sameClan($news, $me);

        return $this->json($this->present($news));
    }

    #[Route('/news', name: 'api_news_create', methods: ['POST'])]
    #[IsGranted(NewsVoter::PUBLISH, message: 'Seuls les membres des branches autorisées (les Communicants) publient des news.')]
    public function create(
        #[CurrentUser] User $me,
        #[MapRequestPayload] NewsRequest $request,
        #[MapUploadedFile(new NewsImage())] ?UploadedFile $image = null,
    ): JsonResponse {
        $news = new News($me->getClan(), $me, $request->cleanTitle(), $request->cleanBody());
        if (null !== $image) {
            $this->storeImage($news, $image);
        }
        $this->em->persist($news);
        $this->em->flush();

        return $this->json($this->present($news), Response::HTTP_CREATED);
    }

    /** POST, as PHP only parses multipart bodies (the image) for POST requests. */
    #[Route('/news/{id}', name: 'api_news_update', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function update(
        News $news,
        #[CurrentUser] User $me,
        #[MapRequestPayload] NewsRequest $request,
        #[MapUploadedFile(new NewsImage())] ?UploadedFile $image = null,
    ): JsonResponse {
        $this->sameClan($news, $me);
        $this->denyAccessUnlessGranted(NewsVoter::EDIT, $news, 'Vous ne pouvez pas modifier cette news.');

        $news->edit($request->cleanTitle(), $request->cleanBody());
        if (null !== $image) {
            $this->storeImage($news, $image);
        } elseif ($request->removeImage) {
            $this->images->remove($news);
        }
        $this->em->flush();

        return $this->json($this->present($news));
    }

    #[Route('/news/{id}', name: 'api_news_delete', requirements: ['id' => Requirement::DIGITS], methods: ['DELETE'])]
    public function delete(News $news, #[CurrentUser] User $me): Response
    {
        $this->sameClan($news, $me);
        $this->denyAccessUnlessGranted(NewsVoter::DELETE, $news, 'Vous ne pouvez pas supprimer cette news.');

        $this->images->remove($news);
        $this->em->remove($news);
        $this->em->flush();

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    /** Images are public, under unguessable names (see NewsImageStorage). */
    #[Route('/news-images/{name}', name: 'api_news_image', requirements: ['name' => NewsImageStorage::NAME_PATTERN], methods: ['GET'], format: 'html')]
    public function image(string $name): BinaryFileResponse
    {
        $path = $this->images->path($name);
        if (!is_file($path)) {
            throw new NotFoundHttpException();
        }

        $response = new BinaryFileResponse($path, headers: ['Content-Type' => 'image/webp']);
        // A new image always gets a new name
        $response->setPublic()->setMaxAge(31536000)->setImmutable();

        return $response;
    }

    private function storeImage(News $news, UploadedFile $image): void
    {
        try {
            $this->images->store($news, $image);
        } catch (\InvalidArgumentException $e) {
            throw new UnprocessableEntityHttpException($e->getMessage(), $e);
        }
    }

    /** @return array{id: ?int, title: string, body: string, imageUrl: ?string, author: ?array{id: ?int, username: string, displayName: string, role: string, avatarUrl: ?string}, createdAt: string, updatedAt: ?string, canEdit: bool, canDelete: bool} */
    private function present(News $news): array
    {
        return $this->presenter->news(
            $news,
            canEdit: $this->isGranted(NewsVoter::EDIT, $news),
            canDelete: $this->isGranted(NewsVoter::DELETE, $news),
        );
    }

    private function sameClan(News $news, User $me): void
    {
        if ($news->getClan()->getId() !== $me->getClan()->getId()) {
            throw new NotFoundHttpException('News introuvable.');
        }
    }
}
