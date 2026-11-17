<?php

namespace App\Tests\Functional;

use App\Enum\OAuthScope;
use App\OAuth\ClientCredentials;
use App\Tests\Support\Jwk;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Conclave as OpenID Connect provider for the Imperium Clan portal (authorization code + PKCE).
 */
final class OidcTest extends ApiTestCase
{
    private const REDIRECT = 'https://portail.test/api/auth/conclave/callback';

    public function testDiscoveryAndKeys(): void
    {
        $config = $this->api('GET', '/.well-known/openid-configuration');
        self::assertResponseIsSuccessful();
        self::assertSame('https://app.test', $config['issuer']);
        self::assertSame('https://app.test/oauth/authorize', $config['authorization_endpoint']);
        self::assertSame(['S256'], $config['code_challenge_methods_supported']);
        self::assertSame(['RS256'], $config['id_token_signing_alg_values_supported']);

        $jwks = $this->api('GET', '/.well-known/jwks.json');
        self::assertSame('RSA', $jwks['keys'][0]['kty']);
        self::assertArrayNotHasKey('d', $jwks['keys'][0], 'never the private part');
    }

    public function testFullSignInFlow(): void
    {
        $leader = $this->registerClan();
        $this->joinBranch($leader['token'], 'communicants', $leader['token']);
        [$clientId, $secret] = $this->client();
        $verifier = ClientCredentials::random(48);

        // 1. The portal sends the member to /oauth/authorize, which hands the request to the app
        $demande = $this->authorize($clientId, $verifier, 'etat-123', 'nonce-456');

        // 2. The signed-in member sees what is shared, then approves
        $shown = $this->api('GET', '/api/oauth/authorization?demande='.urlencode($demande), token: $leader['token']);
        self::assertResponseIsSuccessful();
        self::assertSame('Portail', $shown['client']);
        self::assertSame('portail.test', $shown['host']);
        self::assertSame(['communicants'], $shown['claims']['branches']);
        $decision = $this->api('POST', '/api/oauth/authorization', ['demande' => $demande, 'approve' => true], $leader['token']);
        self::assertResponseIsSuccessful();
        self::assertStringStartsWith(self::REDIRECT.'?', $decision['redirect']);
        parse_str((string) parse_url($decision['redirect'], \PHP_URL_QUERY), $back);
        self::assertSame('etat-123', $back['state']);

        // 3. The portal exchanges the code (Basic + PKCE verifier)
        $tokens = $this->token($clientId, $secret, $back['code'], $verifier);
        self::assertResponseIsSuccessful();
        self::assertSame('Bearer', $tokens['token_type']);

        // 4. ID token: RS256 signature checked with the published key, expected claims
        $claims = $this->verifiedClaims($tokens['id_token']);
        $publicSub = static::getContainer()->get('doctrine.dbal.default_connection')->fetchOne('SELECT public_sub FROM "user" WHERE username = ?', ['papa']);
        self::assertSame('https://app.test', $claims['iss']);
        self::assertSame($clientId, $claims['aud']);
        self::assertSame($publicSub, $claims['sub']);
        self::assertSame('nonce-456', $claims['nonce']);
        self::assertSame('Papa', $claims['name']);
        self::assertSame(['communicants'], $claims['branches']);
        self::assertGreaterThan(time(), $claims['exp']);
        self::assertArrayNotHasKey('email', $claims);

        // 5. userinfo with the access token
        $info = $this->api('GET', '/oauth/userinfo', token: $tokens['access_token']);
        self::assertResponseIsSuccessful();
        self::assertSame($publicSub, $info['sub']);
    }

    public function testCodeIsSingleUseAndReplayRevokesTheTokens(): void
    {
        $leader = $this->registerClan();
        [$clientId, $secret] = $this->client();
        $verifier = ClientCredentials::random(48);
        $code = $this->approvedCode($leader['token'], $clientId, $verifier);

        $first = $this->token($clientId, $secret, $code, $verifier);
        self::assertResponseIsSuccessful();
        $replay = $this->token($clientId, $secret, $code, $verifier);
        self::assertResponseStatusCodeSame(400);
        self::assertSame('invalid_grant', $replay['error']);

        $this->api('GET', '/oauth/userinfo', token: $first['access_token']);
        self::assertResponseStatusCodeSame(401);
    }

    public function testWrongVerifierRedirectOrClientIsRefused(): void
    {
        $leader = $this->registerClan();
        [$clientId, $secret] = $this->client();
        [$otherId, $otherSecret] = $this->client('Autre');
        $verifier = ClientCredentials::random(48);

        self::assertSame('invalid_grant', $this->token($clientId, $secret, $this->approvedCode($leader['token'], $clientId, $verifier), ClientCredentials::random(48))['error']);
        self::assertSame('invalid_grant', $this->token($clientId, $secret, $this->approvedCode($leader['token'], $clientId, $verifier), $verifier, 'https://portail.test/autre')['error']);
        self::assertSame('invalid_grant', $this->token($otherId, $otherSecret, $this->approvedCode($leader['token'], $clientId, $verifier), $verifier)['error']);
    }

    public function testExpiredCodeIsRefused(): void
    {
        $leader = $this->registerClan();
        [$clientId, $secret] = $this->client();
        $verifier = ClientCredentials::random(48);
        $code = $this->approvedCode($leader['token'], $clientId, $verifier);
        static::getContainer()->get('doctrine.dbal.default_connection')->executeStatement("UPDATE oauth_authorization_code SET expires_at = NOW() - INTERVAL '1 second'");

        self::assertSame('invalid_grant', $this->token($clientId, $secret, $code, $verifier)['error']);
    }

    public function testUnknownRedirectUriIsNeverFollowed(): void
    {
        [$clientId] = $this->client();

        $this->client->request('GET', '/oauth/authorize?'.http_build_query(['redirect_uri' => 'https://evil.example/cb'] + $this->params($clientId, ClientCredentials::random(48))));

        self::assertResponseRedirects();
        $location = (string) $this->client->getResponse()->headers->get('Location');
        self::assertStringStartsWith('https://app.test/autoriser?erreur=', $location);
        self::assertStringNotContainsString('evil.example', $location);
    }

    public function testRequestErrorsGoBackToTheClient(): void
    {
        [$clientId] = $this->client();
        $params = $this->params($clientId, ClientCredentials::random(48));

        foreach ([
            ['code_challenge_method' => 'plain'],
            ['scope' => 'profile'],
            ['response_type' => 'token'],
        ] as $change) {
            $this->client->request('GET', '/oauth/authorize?'.http_build_query($change + $params));
            $location = (string) $this->client->getResponse()->headers->get('Location');
            self::assertStringStartsWith(self::REDIRECT.'?error=', $location);
            self::assertStringContainsString('state=etat', $location);
        }
    }

    public function testMemberCanRefuse(): void
    {
        $leader = $this->registerClan();
        [$clientId] = $this->client();
        $demande = $this->authorize($clientId, ClientCredentials::random(48), 'etat', null);

        $decision = $this->api('POST', '/api/oauth/authorization', ['demande' => $demande, 'approve' => false], $leader['token']);

        self::assertStringContainsString('error=access_denied', $decision['redirect']);
        self::assertStringContainsString('state=etat', $decision['redirect']);
    }

    public function testTamperedRequestOrAnonymousConsentIsRefused(): void
    {
        $leader = $this->registerClan();
        [$clientId] = $this->client();
        $demande = $this->authorize($clientId, ClientCredentials::random(48), 'etat', null);

        $this->api('GET', '/api/oauth/authorization?demande='.urlencode($demande.'x'), token: $leader['token']);
        self::assertResponseStatusCodeSame(400);
        $this->api('GET', '/api/oauth/authorization?demande='.urlencode($demande));
        self::assertResponseStatusCodeSame(401);
    }

    public function testMachineClientCannotSignMembersIn(): void
    {
        $leader = $this->registerClan();
        [$clientId, $secret] = static::getContainer()->get(ClientCredentials::class)->createClient('Machine', [OAuthScope::PortalMembers]);

        $this->client->request('GET', '/oauth/authorize?'.http_build_query($this->params($clientId->getClientId(), ClientCredentials::random(48))));
        self::assertStringStartsWith('https://app.test/autoriser?erreur=', (string) $this->client->getResponse()->headers->get('Location'), 'no redirect URI registered');
        unset($leader, $secret);
    }

    public function testCommandsManageRedirectUris(): void
    {
        $application = new \Symfony\Bundle\FrameworkBundle\Console\Application(static::$kernel ?? static::bootKernel());
        $create = new \Symfony\Component\Console\Tester\CommandTester($application->find('app:oauth:client:create'));
        self::assertSame(1, $create->execute(['--name' => 'Portail', '--scope' => ['openid']]), 'openid needs a redirect URI');
        self::assertSame(1, $create->execute(['--name' => 'Portail', '--scope' => ['openid'], '--redirect-uri' => ['http://portail.example/cb']]), 'https only');
        $create->execute(['--name' => 'Portail', '--scope' => ['portal:members']]);
        $create->assertCommandIsSuccessful();

        // Existing machine client of the portal: the sign-in is added, the secret stays the same
        $update = new \Symfony\Component\Console\Tester\CommandTester($application->find('app:oauth:client:update'));
        $update->execute(['id' => '1', '--scope' => ['portal:members', 'openid', 'profile'], '--redirect-uri' => [self::REDIRECT]]);
        $update->assertCommandIsSuccessful();
        $this->em()->clear();
        $client = $this->em()->getRepository(\App\Entity\OAuthClient::class)->find(1);
        self::assertSame(['portal:members', 'openid', 'profile'], $client->getScopes());
        self::assertSame([self::REDIRECT], $client->getRedirectUris());
    }

    // ---- helpers ----

    /** @return array{string, string} */
    private function client(string $name = 'Portail'): array
    {
        [$client, $secret] = static::getContainer()->get(ClientCredentials::class)
            ->createClient($name, [OAuthScope::PortalMembers, OAuthScope::OpenId, OAuthScope::Profile], [self::REDIRECT]);

        return [$client->getClientId(), $secret];
    }

    private function params(string $clientId, string $verifier, string $state = 'etat', ?string $nonce = 'n'): array
    {
        return array_filter([
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => self::REDIRECT,
            'scope' => 'openid profile',
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
        ], static fn ($v) => null !== $v);
    }

    /** @return string the signed request handed to the page /autoriser */
    private function authorize(string $clientId, string $verifier, string $state, ?string $nonce): string
    {
        $this->client->request('GET', '/oauth/authorize?'.http_build_query($this->params($clientId, $verifier, $state, $nonce)));
        self::assertResponseRedirects();
        $location = (string) $this->client->getResponse()->headers->get('Location');
        self::assertStringStartsWith('https://app.test/autoriser?demande=', $location);
        parse_str((string) parse_url($location, \PHP_URL_QUERY), $query);

        return $query['demande'];
    }

    private function approvedCode(string $memberToken, string $clientId, string $verifier): string
    {
        $demande = $this->authorize($clientId, $verifier, 'etat', 'n');
        $decision = $this->api('POST', '/api/oauth/authorization', ['demande' => $demande, 'approve' => true], $memberToken);
        parse_str((string) parse_url($decision['redirect'], \PHP_URL_QUERY), $back);

        return $back['code'];
    }

    private function token(string $clientId, string $secret, string $code, string $verifier, string $redirect = self::REDIRECT): array
    {
        $this->client->request('POST', '/oauth/token', [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirect,
            'code_verifier' => $verifier,
        ], server: ['HTTP_AUTHORIZATION' => 'Basic '.base64_encode($clientId.':'.$secret)]);

        return json_decode((string) $this->client->getResponse()->getContent(), true);
    }

    private function verifiedClaims(string $jwt): array
    {
        [$header, $payload, $signature] = explode('.', $jwt);
        $jwks = $this->api('GET', '/.well-known/jwks.json');
        $kid = json_decode(base64_decode(strtr($header, '-_', '+/')), true)['kid'];
        $jwk = array_values(array_filter($jwks['keys'], static fn (array $k): bool => $k['kid'] === $kid))[0];
        self::assertSame(1, openssl_verify($header.'.'.$payload, base64_decode(strtr($signature, '-_', '+/')), Jwk::toPem($jwk), \OPENSSL_ALGO_SHA256), 'RS256 signature');

        return json_decode(base64_decode(strtr($payload, '-_', '+/')), true);
    }

    private function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }
}
