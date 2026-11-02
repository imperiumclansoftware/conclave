<?php

namespace App\Tests\Functional;

use Symfony\Component\Filesystem\Filesystem;

/**
 * Error messages as the Docker image sends them: without debug, where Symfony replaces the message
 * of an HTTP exception by its status text.
 */
final class ApiErrorTest extends ApiTestCase
{
    private static bool $containerRemoved = false;

    protected function setUp(): void
    {
        parent::setUp();
        // Without debug, the kernel never checks its compiled container (a file of its own, next to the
        // debug one): built again once per run, so that it never tests yesterday's code
        if (!self::$containerRemoved) {
            (new Filesystem())->remove(glob(static::getContainer()->getParameter('kernel.cache_dir').'/App_KernelTestContainer*'));
            self::$containerRemoved = true;
        }
        self::ensureKernelShutdown();
        $this->client = static::createClient(['debug' => false]);
        // This kernel has its own cache pools: the attempts of the previous tests are still counted
        static::getContainer()->get('cache.app')->clear();
        static::getContainer()->get('cache.rate_limiter')->clear();
    }

    public function testKeepsTheMessagesWrittenForTheMembers(): void
    {
        $error = $this->api('POST', '/api/setup/verify', ['code' => 'AAAA-AAAA']);

        self::assertResponseStatusCodeSame(403);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
        self::assertStringStartsWith("Code d'installation incorrect.", $error['detail']);
    }

    public function testKeepsTheHeadersOfTheException(): void
    {
        for ($i = 0; $i < 10; ++$i) {
            $this->api('POST', '/api/setup/verify', ['code' => 'AAAA-AAA'.$i]);
        }
        $error = $this->api('POST', '/api/setup/verify', ['code' => 'AAAA-AAAA']);

        self::assertResponseStatusCodeSame(429);
        self::assertTrue($this->client->getResponse()->headers->has('Retry-After'));
        self::assertSame('Trop de tentatives : réessayez dans quelques minutes.', $error['detail']);
    }

    public function testKeepsTheMessagesOfTheAccessRules(): void
    {
        $leader = $this->registerClan();

        $error = $this->api('GET', '/api/me/games', token: $leader['token']);

        self::assertResponseStatusCodeSame(403);
        self::assertSame('Les jeux sont réservés aux membres de la branche Joueurs.', $error['detail']);
    }

    public function testHidesTheMessagesOfTheFramework(): void
    {
        $error = $this->api('GET', '/api/nothing-here');
        self::assertResponseStatusCodeSame(404);
        self::assertSame('Not Found', $error['detail']);

        // "Access Denied. The user doesn't have ROLE_LEADER."
        $leader = $this->registerClan();
        ['token' => $member] = $this->addMember($leader['token'], 'lea');
        $error = $this->api('GET', '/api/admin/branches', token: $member);
        self::assertResponseStatusCodeSame(403);
        self::assertSame('Forbidden', $error['detail']);
    }

    public function testKeepsTheViolationsOfTheValidation(): void
    {
        $error = $this->api('POST', '/api/setup/verify', ['code' => '']);

        self::assertResponseStatusCodeSame(422);
        self::assertNotEmpty($error['violations']);
    }
}
