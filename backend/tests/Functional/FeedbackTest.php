<?php

namespace App\Tests\Functional;

final class FeedbackTest extends ApiTestCase
{
    /** @var array<string, array{0: ?string, 1: ?string}> environment and server values before the test */
    private array $savedEnv = [];

    protected function tearDown(): void
    {
        foreach ($this->savedEnv as $name => [$env, $server]) {
            if (null === $env) {
                unset($_ENV[$name]);
            } else {
                $_ENV[$name] = $env;
            }
            if (null === $server) {
                unset($_SERVER[$name]);
            } else {
                $_SERVER[$name] = $server;
            }
        }
        parent::tearDown();
    }

    public function testNoWidgetWhileAvengerIsNotConfigured(): void
    {
        $token = $this->registerClan()['token'];

        self::assertSame(['widgetUrl' => null, 'product' => null], $this->api('GET', '/api/feedback', token: $token));
    }

    public function testTheWidgetOfTheConfiguredAvenger(): void
    {
        $token = $this->registerClan()['token'];
        $this->withEnv(['AVENGER_URL' => 'https://avenger.example.org/absproxy/4300/']);

        self::assertSame(
            ['widgetUrl' => 'https://avenger.example.org/absproxy/4300/widget.js', 'product' => 'conclave'],
            $this->api('GET', '/api/feedback', token: $token),
        );
    }

    public function testAnAddressThatIsNotHttpIsIgnored(): void
    {
        $token = $this->registerClan()['token'];
        $this->withEnv(['AVENGER_URL' => 'javascript:alert(1)', 'AVENGER_PRODUCT' => 'autre']);

        self::assertNull($this->api('GET', '/api/feedback', token: $token)['widgetUrl']);
    }

    public function testMembersOnly(): void
    {
        $this->api('GET', '/api/feedback');
        self::assertResponseStatusCodeSame(401);
    }

    /**
     * Environment variables are read when the container is built: rebuild it for the next requests.
     *
     * @param array<string, string> $variables
     */
    private function withEnv(array $variables): void
    {
        foreach ($variables as $name => $value) {
            $this->savedEnv[$name] ??= [$_ENV[$name] ?? null, $_SERVER[$name] ?? null];
            // $_ENV first: that is where .env puts its defaults, and where the container looks first
            $_ENV[$name] = $_SERVER[$name] = $value;
        }
        $this->client->getKernel()->shutdown();
        $this->client->getKernel()->boot();
    }
}
