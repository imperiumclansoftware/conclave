<?php

namespace App\Tests\Functional;

use App\Setup\SetupCode;
use App\Tests\Support\HttpClientMock;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class ApiTestCase extends WebTestCase
{
    protected KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        static::getContainer()->get('cache.app')->clear();
        static::getContainer()->get('cache.rate_limiter')->clear();
        HttpClientMock::reset();

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $schemaTool = new SchemaTool($em);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    /**
     * Sends a JSON request and returns the decoded response (null when empty).
     */
    protected function api(string $method, string $uri, ?array $body = null, ?string $token = null): mixed
    {
        $headers = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];
        if (null !== $token) {
            $headers['HTTP_AUTHORIZATION'] = 'Bearer '.$token;
        }

        $this->client->request($method, $uri, server: $headers, content: null !== $body ? json_encode($body) : null);
        $content = $this->client->getResponse()->getContent();

        return '' === $content ? null : json_decode($content, true);
    }

    /**
     * @return array{token: string, user: array} the leader's token and profile
     */
    protected function registerClan(string $clanName = 'Martin', string $username = 'papa'): array
    {
        // Installation wizard: the clan and its first leader
        $result = $this->api('POST', '/api/setup', [
            'code' => static::getContainer()->get(SetupCode::class)->current(),
            'clanName' => $clanName,
            'username' => $username,
            'displayName' => ucfirst($username),
            'email' => "$username@example.com",
            'password' => 'motdepasse',
        ]);
        self::assertResponseStatusCodeSame(201);

        return $result;
    }

    /**
     * Signs up once the clan exists: the account waits for a leader's approval.
     *
     * @return int the id of the pending account
     */
    protected function signUp(string $username): int
    {
        $result = $this->api('POST', '/api/register', [
            'username' => $username,
            'displayName' => ucfirst($username),
            'email' => "$username@example.com",
            'password' => 'motdepasse',
        ]);
        self::assertResponseStatusCodeSame(202);
        self::assertSame(['status' => 'pending'], $result);

        return (int) static::getContainer()->get('doctrine.dbal.default_connection')
            ->fetchOne('SELECT id FROM "user" WHERE username = ?', [$username]);
    }

    /**
     * @return array{token: string, user: array} the new member's token and profile
     */
    protected function addMember(string $leaderToken, string $username, string $role = 'member'): array
    {
        $user = $this->api('POST', '/api/admin/members', [
            'username' => $username,
            'displayName' => ucfirst($username),
            'email' => "$username@example.com",
            'password' => 'motdepasse',
            'role' => $role,
        ], $leaderToken);
        self::assertResponseStatusCodeSame(201);

        return ['token' => $this->login($username), 'user' => $user];
    }

    /**
     * Activates the branch with this coat of arms and makes these members join it (leaders join
     * directly, the others need an approval: use leader tokens or branches without approval).
     *
     * @return int the id of the branch
     */
    protected function joinBranch(string $leaderToken, string $crest, string ...$tokens): int
    {
        $branches = array_column($this->api('GET', '/api/admin/branches', token: $leaderToken), null, 'crest');
        $id = $branches[$crest]['id'];
        $this->api('PUT', "/api/admin/branches/$id/active", ['active' => true], $leaderToken);
        foreach ($tokens as $token) {
            $this->api('PUT', "/api/clan/branches/$id/membership", token: $token);
            self::assertResponseIsSuccessful();
        }

        return $id;
    }

    protected function login(string $username, string $password = 'motdepasse'): string
    {
        $result = $this->api('POST', '/api/login', ['username' => $username, 'password' => $password]);
        self::assertResponseIsSuccessful();

        return $result['token'];
    }

    /**
     * Links a Discord account through the OAuth flow ("install" also connects the clan's server).
     */
    protected function linkDiscord(string $token, string $discordId, string $purpose = 'link'): mixed
    {
        $authorize = $this->api('POST', '/api/discord/authorize', ['purpose' => $purpose], $token);
        self::assertResponseIsSuccessful();
        parse_str((string) parse_url($authorize['url'], \PHP_URL_QUERY), $query);

        $code = ('install' === $purpose ? 'install-' : 'code-').$discordId;

        return $this->api('POST', '/api/discord/callback', ['code' => $code, 'state' => $query['state']], $token);
    }
}
