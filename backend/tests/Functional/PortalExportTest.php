<?php

namespace App\Tests\Functional;

use App\Entity\OAuthAccessToken;
use App\Entity\OAuthClient;
use App\Enum\OAuthScope;
use App\OAuth\ClientCredentials;
use App\Tests\Support\HttpClientMock;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Export of the members to the clan's public portal, with the exact contract the portal expects
 * (OAuth2 client credentials, then GET /api/portal/members).
 */
final class PortalExportTest extends ApiTestCase
{
    private const SUB = '/^[A-Za-z0-9_-]{1,64}$/';

    protected function setUp(): void
    {
        parent::setUp();
        // The portal avatars cache lives on disk: start each test without it
        (new \Symfony\Component\Filesystem\Filesystem())->remove(static::getContainer()->getParameter('kernel.project_dir').'/var/avatars/test/portal');
    }

    public function testFullFlowExportsOnlyConsentingMembersWithTheContractFields(): void
    {
        $leader = $this->registerClan();
        $ezio = $this->addMember($leader['token'], 'ezio');
        $this->addMember($leader['token'], 'altair'); // never opts in
        $this->joinBranch($leader['token'], 'players', $leader['token'], $ezio['token']);
        $this->joinBranch($leader['token'], 'developers', $ezio['token']);
        $this->api('PUT', '/api/me/profile', ['displayName' => 'Ezio Auditore', 'bio' => 'Maître assassin', 'email' => 'ezio@example.com'], $ezio['token']);
        $this->optIn($leader['token']);
        $this->optIn($ezio['token']);

        [$clientId, $secret] = $this->createOAuthClient();
        $token = $this->requestToken($clientId, $secret);
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));
        self::assertSame('Bearer', $token['token_type']);
        self::assertSame(300, $token['expires_in']);
        self::assertNotSame('', $token['access_token']);

        $result = $this->api('GET', '/api/portal/members', token: $token['access_token']);
        self::assertResponseIsSuccessful();
        self::assertSame(['items'], array_keys($result));
        self::assertCount(2, $result['items']);

        $byPseudo = array_column($result['items'], null, 'pseudo');
        self::assertSame(['Ezio Auditore', 'Papa'], array_keys($byPseudo));
        $item = $byPseudo['Ezio Auditore'];
        self::assertSame(['sub', 'pseudo', 'title', 'bio', 'branches', 'avatarUrl'], array_keys($item));
        self::assertMatchesRegularExpression(self::SUB, $item['sub']);
        self::assertSame('Maître assassin', $item['bio']);
        self::assertSame(['developers', 'players'], $item['branches']);
        self::assertNull($item['title']);
        self::assertNull($item['avatarUrl']);
        self::assertSame(['players'], $byPseudo['Papa']['branches']);

        // Nothing private leaks: no e-mail, username, id, role, Discord, presence
        $json = (string) $this->client->getResponse()->getContent();
        foreach (['ezio@example.com', '"ezio"', 'email', 'username', 'role', 'discord', 'lastSeen', 'Altair'] as $private) {
            self::assertStringNotContainsStringIgnoringCase($private, $json);
        }

        // The sub is stable and is not the database id
        $again = $this->api('GET', '/api/portal/members', token: $token['access_token']);
        self::assertSame($result, $again);
        self::assertNotSame((string) $ezio['user']['id'], $item['sub']);
    }

    public function testPendingAccountIsNotExportedEvenIfItOptedIn(): void
    {
        $leader = $this->registerClan();
        $pending = $this->signUp('ezio');
        $this->em()->getConnection()->executeStatement('UPDATE "user" SET public_profile = true WHERE id = ?', [$pending]);

        self::assertSame([], $this->members()['items']);
        unset($leader);
    }

    public function testOptInIsOffByDefaultAndCanBeWithdrawn(): void
    {
        $leader = $this->registerClan();
        self::assertFalse($this->api('GET', '/api/me', token: $leader['token'])['publicProfile'] ?? true);
        self::assertSame([], $this->members()['items']);

        $me = $this->api('PUT', '/api/me/public-profile', ['visible' => true], $leader['token']);
        self::assertTrue($me['publicProfile']);
        self::assertCount(1, $this->members()['items']);

        $me = $this->api('PUT', '/api/me/public-profile', ['visible' => false], $leader['token']);
        self::assertFalse($me['publicProfile']);
        self::assertSame([], $this->members()['items']);

        $this->api('PUT', '/api/me/public-profile', [], $leader['token']);
        self::assertResponseStatusCodeSame(422);
    }

    public function testWrongSecretIsRejected(): void
    {
        [$clientId] = $this->createOAuthClient();

        $result = $this->requestToken($clientId, 'not-the-secret');

        self::assertResponseStatusCodeSame(401);
        self::assertSame('invalid_client', $result['error']);
        self::assertStringStartsWith('Basic', (string) $this->client->getResponse()->headers->get('WWW-Authenticate'));
        $this->requestToken('cc_unknown', 'whatever');
        self::assertResponseStatusCodeSame(401);
    }

    public function testCredentialsAreAcceptedInBasicAuthOnly(): void
    {
        [$clientId, $secret] = $this->createOAuthClient();

        $this->client->request('POST', '/oauth/token', ['grant_type' => 'client_credentials', 'client_id' => $clientId, 'client_secret' => $secret]);

        self::assertResponseStatusCodeSame(401);
        self::assertSame('invalid_client', $this->json()['error']);
    }

    public function testRevokedClientGetsNoTokenAndItsTokensStopWorking(): void
    {
        [$clientId, $secret] = $this->createOAuthClient();
        $token = $this->requestToken($clientId, $secret)['access_token'];

        $tester = $this->command('app:oauth:client:revoke');
        $tester->execute(['id' => $clientId]);
        $tester->assertCommandIsSuccessful();

        $this->api('GET', '/api/portal/members', token: $token);
        self::assertResponseStatusCodeSame(401);
        self::assertSame('invalid_token', $this->json()['error']);
        $this->requestToken($clientId, $secret);
        self::assertResponseStatusCodeSame(401);
    }

    public function testScopeAndGrantTypeAreChecked(): void
    {
        [$clientId, $secret] = $this->createOAuthClient();

        self::assertSame('invalid_scope', $this->requestToken($clientId, $secret, 'admin')['error']);
        self::assertResponseStatusCodeSame(400);
        self::assertSame('unsupported_grant_type', $this->requestToken($clientId, $secret, 'portal:members', 'password')['error']);
        self::assertResponseStatusCodeSame(400);
        self::assertSame('invalid_request', $this->requestToken($clientId, $secret, 'portal:members', '')['error']);
        self::assertResponseStatusCodeSame(400);

        // No scope asked: the client's scopes
        self::assertSame('portal:members', $this->requestToken($clientId, $secret, null)['scope']);
    }

    public function testTokenWithoutTheScopeIsForbidden(): void
    {
        $this->createOAuthClient();
        $client = $this->em()->getRepository(OAuthClient::class)->findOneBy([]);
        $this->em()->persist(new OAuthAccessToken($client, hash('sha256', 'scopeless-token'), [], new \DateTimeImmutable('+5 minutes')));
        $this->em()->flush();

        $result = $this->api('GET', '/api/portal/members', token: 'scopeless-token');

        self::assertResponseStatusCodeSame(403);
        self::assertSame('insufficient_scope', $result['error']);
    }

    public function testExpiredTokenIsRejected(): void
    {
        [$clientId, $secret] = $this->createOAuthClient();
        $token = $this->requestToken($clientId, $secret)['access_token'];
        $this->em()->getConnection()->executeStatement("UPDATE oauth_access_token SET expires_at = NOW() - INTERVAL '1 second'");

        $result = $this->api('GET', '/api/portal/members', token: $token);

        self::assertResponseStatusCodeSame(401);
        self::assertSame('invalid_token', $result['error']);
        self::assertStringContainsString('invalid_token', (string) $this->client->getResponse()->headers->get('WWW-Authenticate'));
    }

    public function testNoTokenOrAMemberTokenIsRejected(): void
    {
        $leader = $this->registerClan();

        $this->api('GET', '/api/portal/members');
        self::assertResponseStatusCodeSame(401);

        // A member's session (JWT) is not a portal token
        $this->api('GET', '/api/portal/members', token: $leader['token']);
        self::assertResponseStatusCodeSame(401);
    }

    public function testTokenEndpointIsRateLimited(): void
    {
        [$clientId] = $this->createOAuthClient();
        for ($i = 0; $i < 10; ++$i) {
            $this->requestToken($clientId, 'wrong');
        }

        self::assertSame('temporarily_unavailable', $this->requestToken($clientId, 'wrong')['error']);
        self::assertResponseStatusCodeSame(429);
    }

    public function testUploadedAvatarIsAnAbsoluteUrlOnConclaveServedWithoutToken(): void
    {
        $leader = $this->registerClan();
        $this->client->request('POST', '/api/me/avatar', files: ['avatar' => $this->image()], server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$leader['token'],
            'HTTP_ACCEPT' => 'application/json',
        ]);
        self::assertResponseIsSuccessful();
        $this->optIn($leader['token']);

        $url = $this->members()['items'][0]['avatarUrl'];

        self::assertMatchesRegularExpression('#^https://app\.test/api/avatars/[a-f0-9]{32}\.webp$#', $url);
        $this->client->request('GET', (string) parse_url($url, \PHP_URL_PATH));
        self::assertResponseIsSuccessful();
        self::assertSame('image/webp', $this->client->getResponse()->headers->get('Content-Type'));
    }

    public function testGravatarIsProxiedFromConclaveUnderASignedUrl(): void
    {
        $leader = $this->registerClan();
        $this->api('PUT', '/api/me/avatar/gravatar', ['email' => 'ezio@example.com'], $leader['token']);
        self::assertResponseIsSuccessful();
        $this->optIn($leader['token']);
        $item = $this->members()['items'][0];

        self::assertMatchesRegularExpression('#^https://app\.test/api/portal/avatars/'.preg_quote($item['sub'], '#').'/[a-f0-9]{32}\.webp$#', $item['avatarUrl']);
        $path = (string) parse_url($item['avatarUrl'], \PHP_URL_PATH);

        // Public (the portal sends no credentials), 256×256 WebP with its length, no redirect
        $this->client->request('GET', $path);
        $response = $this->client->getResponse();
        self::assertResponseStatusCodeSame(200);
        self::assertSame('image/webp', $response->headers->get('Content-Type'));
        $file = $response->getFile()->getPathname();
        self::assertSame([256, 256], \array_slice(getimagesize($file), 0, 2));
        self::assertLessThanOrEqual(2 * 1024 * 1024, filesize($file));

        // Cached: the portal asks every 15 minutes, Gravatar is fetched once
        $this->client->request('GET', $path);
        self::assertResponseIsSuccessful();
        self::assertSame(1, HttpClientMock::$gravatarDownloads);

        // A forged signature, or a member who withdrew, gives nothing
        $this->client->request('GET', preg_replace('#/[a-f0-9]{32}\.webp$#', '/'.str_repeat('0', 32).'.webp', $path));
        self::assertResponseStatusCodeSame(404);
        $this->api('PUT', '/api/me/public-profile', ['visible' => false], $leader['token']);
        $this->client->request('GET', $path);
        self::assertResponseStatusCodeSame(404);
    }

    public function testCommandsCreateListAndRevoke(): void
    {
        $create = $this->command('app:oauth:client:create');
        $create->execute(['--name' => 'Portail', '--scope' => ['portal:members']]);
        $create->assertCommandIsSuccessful();
        $output = $create->getDisplay();
        self::assertMatchesRegularExpression('/client_id\s+cc_[A-Za-z0-9_-]+/', $output);
        preg_match('/client_secret\s+(\S+)/', $output, $m);
        $client = $this->em()->getRepository(OAuthClient::class)->findOneBy(['name' => 'Portail']);
        self::assertNotNull($client);
        self::assertStringNotContainsString($m[1], $client->getSecretHash());
        self::assertStringStartsWith('$argon2id$', $client->getSecretHash());
        self::assertSame(['portal:members'], $client->getScopes());

        $bad = $this->command('app:oauth:client:create');
        self::assertSame(1, $bad->execute(['--name' => 'X', '--scope' => ['admin']]));

        $list = $this->command('app:oauth:client:list');
        $list->execute([]);
        self::assertStringContainsString('Portail', $list->getDisplay());
        self::assertStringContainsString('actif', $list->getDisplay());

        $revoke = $this->command('app:oauth:client:revoke');
        $revoke->execute(['id' => (string) $client->getId()]);
        $revoke->assertCommandIsSuccessful();
        $this->em()->clear();
        self::assertFalse($this->em()->getRepository(OAuthClient::class)->find($client->getId())->isActive());
    }

    // ---- helpers ----

    /** @return array{string, string} client_id, secret */
    private function createOAuthClient(): array
    {
        [$client, $secret] = static::getContainer()->get(ClientCredentials::class)->createClient('Portail', [OAuthScope::PortalMembers]);

        return [$client->getClientId(), $secret];
    }

    /** The portal's exact request: Basic credentials, form body */
    private function requestToken(string $clientId, string $secret, ?string $scope = 'portal:members', string $grantType = 'client_credentials'): array
    {
        $body = ['grant_type' => $grantType];
        if (null !== $scope) {
            $body['scope'] = $scope;
        }
        $this->client->request('POST', '/oauth/token', $body, server: [
            'HTTP_AUTHORIZATION' => 'Basic '.base64_encode($clientId.':'.$secret),
            'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
        ]);

        return $this->json();
    }

    private function members(): array
    {
        [$clientId, $secret] = $this->createOAuthClient();
        $result = $this->api('GET', '/api/portal/members', token: $this->requestToken($clientId, $secret)['access_token']);
        self::assertResponseIsSuccessful();

        return $result;
    }

    private function optIn(string $token): void
    {
        $this->api('PUT', '/api/me/public-profile', ['visible' => true], $token);
        self::assertResponseIsSuccessful();
    }

    private function json(): array
    {
        return json_decode((string) $this->client->getResponse()->getContent(), true);
    }

    private function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    private function command(string $name): \Symfony\Component\Console\Tester\CommandTester
    {
        $application = new \Symfony\Bundle\FrameworkBundle\Console\Application(static::$kernel ?? static::bootKernel());

        return new \Symfony\Component\Console\Tester\CommandTester($application->find($name));
    }

    private function image(): UploadedFile
    {
        $image = imagecreatetruecolor(300, 300);
        $file = tempnam(sys_get_temp_dir(), 'avatar').'.png';
        imagepng($image, $file);

        return new UploadedFile($file, 'avatar.png', 'image/png', null, true);
    }
}
