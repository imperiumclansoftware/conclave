<?php

namespace App\Tests\Functional;

use App\Tests\Support\HttpClientMock;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class NewsTest extends ApiTestCase
{
    public function testOnlyApprovedCommunicantsPublishNewsReadByTheWholeClan(): void
    {
        $leader = $this->registerClan();
        ['token' => $lea, 'user' => $leaUser] = $this->addMember($leader['token'], 'lea');
        $tom = $this->addMember($leader['token'], 'tom')['token'];
        $communicants = $this->communicants($leader['token'], leaderJoins: false);

        // Not even a leader publishes outside of the branch
        self::assertFalse($this->api('GET', '/api/me', token: $leader['token'])['canPublishNews']);
        $this->post('/api/news', ['title' => 'Bonjour', 'body' => 'Texte'], $leader['token']);
        self::assertResponseStatusCodeSame(403);

        // Asking to join is not enough: a leader approves
        $asked = $this->api('PUT', "/api/clan/branches/{$communicants['id']}/membership", token: $lea);
        self::assertResponseStatusCodeSame(202);
        self::assertSame([[], [$leaUser['id']]], [$asked['memberIds'], $asked['pendingIds']]);
        self::assertCount(1, HttpClientMock::publishedOfType('signups'), 'Leaders refresh their badge');
        self::assertFalse($this->api('GET', '/api/me', token: $lea)['canPublishNews']);
        $this->post('/api/news', ['title' => 'Bonjour', 'body' => 'Texte'], $lea);
        self::assertResponseStatusCodeSame(403);

        $requests = $this->api('GET', '/api/admin/branch-requests', token: $leader['token']);
        self::assertSame([['Communicants', 'lea']], array_map(static fn (array $r): array => [$r['branch']['name'], $r['member']['username']], $requests));
        $approved = $this->api('POST', "/api/admin/branch-requests/{$requests[0]['id']}/approve", token: $leader['token']);
        self::assertSame([[$leaUser['id']], []], [$approved['memberIds'], $approved['pendingIds']]);
        self::assertCount(1, HttpClientMock::publishedOfType('branches'), 'The member is told');
        self::assertTrue($this->api('GET', '/api/me', token: $lea)['canPublishNews']);

        $news = $this->post('/api/news', ['title' => '  Soirée   Valhalla ', 'body' => "Rendez-vous samedi.\r\n\r\n\r\n\r\nVenez nombreux !"], $lea, $this->image(1200, 600));
        self::assertResponseStatusCodeSame(201);
        self::assertSame(['Soirée Valhalla', "Rendez-vous samedi.\n\nVenez nombreux !", 'lea', true, true],
            [$news['title'], $news['body'], $news['author']['username'], $news['canEdit'], $news['canDelete']]);
        self::assertMatchesRegularExpression('#^api/news-images/[a-f0-9]{32}\.webp$#', $news['imageUrl']);

        // The image is public and resized
        $this->client->request('GET', '/'.$news['imageUrl']);
        self::assertResponseIsSuccessful();
        self::assertSame([1200, 600], \array_slice(getimagesizefromstring($this->client->getInternalResponse()->getContent()), 0, 2));

        // Read by everybody, without the right to change it
        $list = $this->api('GET', '/api/news', token: $tom);
        self::assertSame([['Soirée Valhalla', false, false]], array_map(static fn (array $n): array => [$n['title'], $n['canEdit'], $n['canDelete']], $list['items']));
        self::assertFalse($list['hasMore']);
        $this->post("/api/news/{$news['id']}", ['title' => 'Piraté', 'body' => '…'], $tom);
        self::assertResponseStatusCodeSame(403);
        $this->api('DELETE', "/api/news/{$news['id']}", token: $tom);
        self::assertResponseStatusCodeSame(403);

        // Leaders moderate: they delete, but do not rewrite somebody else's news
        $seenByLeader = $this->api('GET', "/api/news/{$news['id']}", token: $leader['token']);
        self::assertSame([false, true], [$seenByLeader['canEdit'], $seenByLeader['canDelete']]);

        // A deactivated branch gives no right, until it is active again
        $this->api('PUT', "/api/admin/branches/{$communicants['id']}/active", ['active' => false], $leader['token']);
        self::assertFalse($this->api('GET', '/api/me', token: $lea)['canPublishNews']);
        $this->api('PUT', "/api/admin/branches/{$communicants['id']}/active", ['active' => true], $leader['token']);
        self::assertTrue($this->api('GET', '/api/me', token: $lea)['canPublishNews']);

        // Leaving the branch takes the right away
        $this->api('DELETE', "/api/clan/branches/{$communicants['id']}/membership", token: $lea);
        self::assertFalse($this->api('GET', "/api/news/{$news['id']}", token: $lea)['canEdit']);
    }

    public function testLeadersRejectRequestsAndMembersCancelThem(): void
    {
        $leader = $this->registerClan();
        $communicants = $this->communicants($leader['token'], leaderJoins: false);
        $lea = $this->addMember($leader['token'], 'lea')['token'];
        $tom = $this->addMember($leader['token'], 'tom')['token'];

        $this->api('PUT', "/api/clan/branches/{$communicants['id']}/membership", token: $lea);
        $this->api('PUT', "/api/clan/branches/{$communicants['id']}/membership", token: $lea);
        $this->api('PUT', "/api/clan/branches/{$communicants['id']}/membership", token: $tom);
        $requests = $this->api('GET', '/api/admin/branch-requests', token: $leader['token']);
        self::assertSame(['lea', 'tom'], array_map(static fn (array $r): string => $r['member']['username'], $requests), 'Asking twice changes nothing');

        $rejected = $this->api('POST', "/api/admin/branch-requests/{$requests[0]['id']}/reject", token: $leader['token']);
        self::assertSame([[], [$requests[1]['member']['id']]], [$rejected['memberIds'], $rejected['pendingIds']]);
        $cancelled = $this->api('DELETE', "/api/clan/branches/{$communicants['id']}/membership", token: $tom);
        self::assertSame([], $cancelled['pendingIds']);
        self::assertSame([], $this->api('GET', '/api/admin/branch-requests', token: $leader['token']));

        // Only leaders answer
        $this->api('PUT', "/api/clan/branches/{$communicants['id']}/membership", token: $tom);
        $request = $this->api('GET', '/api/admin/branch-requests', token: $leader['token'])[0];
        $this->api('POST', "/api/admin/branch-requests/{$request['id']}/approve", token: $tom);
        self::assertResponseStatusCodeSame(403);
    }

    public function testAuthorsEditTheirNewsAndGoBackToTheDefaultVisual(): void
    {
        $leader = $this->registerClan();
        $this->communicants($leader['token']);
        $news = $this->post('/api/news', ['title' => 'Annonce', 'body' => 'Texte'], $leader['token'], $this->image(400, 300));
        self::assertNotNull($news['imageUrl']);
        self::assertNull($news['updatedAt']);

        $edited = $this->post("/api/news/{$news['id']}", ['title' => 'Annonce modifiée', 'body' => 'Nouveau texte'], $leader['token']);
        self::assertSame(['Annonce modifiée', 'Nouveau texte', $news['imageUrl']], [$edited['title'], $edited['body'], $edited['imageUrl']], 'The image is kept');
        self::assertNotNull($edited['updatedAt']);

        $edited = $this->post("/api/news/{$news['id']}", ['title' => 'Annonce', 'body' => 'Texte', 'removeImage' => '1'], $leader['token']);
        self::assertNull($edited['imageUrl']);
        $this->client->request('GET', '/'.$news['imageUrl']);
        self::assertResponseStatusCodeSame(404, 'The file is removed');

        $this->api('DELETE', "/api/news/{$news['id']}", token: $leader['token']);
        self::assertResponseStatusCodeSame(204);
        self::assertSame([], $this->api('GET', '/api/news', token: $leader['token'])['items']);
    }

    public function testNewsArePagedFromTheMostRecent(): void
    {
        $leader = $this->registerClan();
        $this->communicants($leader['token']);
        for ($i = 1; $i <= 14; ++$i) {
            $this->post('/api/news', ['title' => "News $i", 'body' => 'Texte'], $leader['token']);
        }

        $first = $this->api('GET', '/api/news', token: $leader['token']);
        self::assertSame(['News 14', 'News 3'], [$first['items'][0]['title'], $first['items'][11]['title']]);
        self::assertTrue($first['hasMore']);

        $next = $this->api('GET', '/api/news?before='.$first['items'][11]['id'], token: $leader['token']);
        self::assertSame(['News 2', 'News 1'], array_column($next['items'], 'title'));
        self::assertFalse($next['hasMore']);
    }

    public function testInvalidNewsAreRejected(): void
    {
        $leader = $this->registerClan();
        $this->communicants($leader['token']);

        $this->post('/api/news', ['title' => ' ', 'body' => 'Texte'], $leader['token']);
        self::assertResponseStatusCodeSame(422);
        $this->post('/api/news', ['title' => 'Titre', 'body' => str_repeat('a', 10001)], $leader['token']);
        self::assertResponseStatusCodeSame(422);
        $file = tempnam(sys_get_temp_dir(), 'news');
        file_put_contents($file, 'pas une image');
        $this->post('/api/news', ['title' => 'Titre', 'body' => 'Texte'], $leader['token'], new UploadedFile($file, 'image.png', 'image/png', null, true));
        self::assertResponseStatusCodeSame(422);
        self::assertSame([], $this->api('GET', '/api/news', token: $leader['token'])['items']);
    }

    /** The branch that publishes the news, with the leader in it by default (leaders join without approval). */
    private function communicants(string $leaderToken, bool $leaderJoins = true): array
    {
        $all = $this->api('GET', '/api/admin/branches', token: $leaderToken);
        $branch = array_values(array_filter($all, static fn (array $b): bool => 'communicants' === $b['crest']))[0];
        self::assertSame([true, true], [$branch['publishesNews'], $branch['approvalRequired']]);
        $branch = $this->api('PUT', "/api/admin/branches/{$branch['id']}/active", ['active' => true], $leaderToken);
        if (!$leaderJoins) {
            return $branch;
        }
        $joined = $this->api('PUT', "/api/clan/branches/{$branch['id']}/membership", token: $leaderToken);
        self::assertResponseStatusCodeSame(200);
        self::assertCount(1, $joined['memberIds']);

        return $branch;
    }

    /** Multipart request, like the browser's FormData */
    private function post(string $uri, array $fields, string $token, ?UploadedFile $image = null): mixed
    {
        $this->client->request('POST', $uri, $fields, null !== $image ? ['image' => $image] : [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
        $content = $this->client->getResponse()->getContent();

        return '' === $content ? null : json_decode($content, true);
    }

    private function image(int $width, int $height): UploadedFile
    {
        $file = tempnam(sys_get_temp_dir(), 'news').'.png';
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 163, 23, 29));
        imagepng($image, $file);

        return new UploadedFile($file, 'image.png', 'image/png', null, true);
    }
}
