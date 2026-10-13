<?php

namespace App\Tests\Functional;

use App\Settings\AppSettings;
use App\Setup\SetupCode;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class SetupTest extends ApiTestCase
{
    public function testTheWizardCreatesTheClanAndItsFirstLeaderWithTheServerCode(): void
    {
        self::assertSame(['initialized' => false, 'clanName' => null], $this->api('GET', '/api/setup'));
        $code = $this->code()->current();
        self::assertMatchesRegularExpression('/^[A-Z2-9]{4}-[A-Z2-9]{4}$/', $code);

        // The console gives the code to whoever has access to the server
        $console = new CommandTester((new Application(self::$kernel))->find('app:setup:code'));
        $console->execute([]);
        self::assertStringContainsString($code, $console->getDisplay());

        // Checked on its own first; case, spaces and dashes do not matter
        $this->api('POST', '/api/setup/verify', ['code' => 'AAAA-AAAA']);
        self::assertResponseStatusCodeSame(403);
        $this->api('POST', '/api/setup/verify', ['code' => ' '.strtolower(str_replace('-', ' ', $code))]);
        self::assertResponseStatusCodeSame(204);

        $result = $this->api('POST', '/api/setup', $this->install($code) + ['appUrl' => 'https://clan.example.com']);
        self::assertResponseStatusCodeSame(201);
        self::assertNotEmpty($result['token']);
        self::assertSame(['papa', 'leader', 'Imperium', 'papa@example.com'], [$result['user']['username'], $result['user']['role'], $result['user']['clan']['name'], $result['user']['email']]);
        self::assertSame('papa', $this->api('GET', '/api/me', token: $result['token'])['username']);
        self::assertSame(['initialized' => true, 'clanName' => 'Imperium'], $this->api('GET', '/api/setup'));
        self::assertSame('https://clan.example.com/', self::getContainer()->get(AppSettings::class)->get(AppSettings::APP_URL));

        // Once only, and the code is gone
        $this->api('POST', '/api/setup', $this->install($code));
        self::assertResponseStatusCodeSame(409);
        $console->execute([]);
        self::assertStringContainsString('déjà installée', $console->getDisplay());
    }

    public function testNothingIsCreatedWithoutTheRightCode(): void
    {
        $code = $this->code()->current();

        $result = $this->api('POST', '/api/setup', $this->install('ZZZZ-ZZZZ'));
        self::assertResponseStatusCodeSame(403);
        self::assertStringContainsString('app:setup:code', $result['detail']);

        // Signing up does not create the clan any more
        $result = $this->api('POST', '/api/register', ['username' => 'intrus', 'displayName' => 'Intrus', 'email' => 'intrus@example.com', 'password' => 'motdepasse']);
        self::assertResponseStatusCodeSame(409);
        self::assertStringContainsString('assistant d\'installation', $result['detail']);
        self::assertFalse($this->api('GET', '/api/setup')['initialized']);

        // Validation of the form, with the right code
        $this->api('POST', '/api/setup', ['clanName' => ' '] + $this->install($code));
        self::assertResponseStatusCodeSame(422);
        $this->api('POST', '/api/setup', ['password' => 'court'] + $this->install($code));
        self::assertResponseStatusCodeSame(422);
        $this->api('POST', '/api/setup', ['appUrl' => 'pas une adresse'] + $this->install($code));
        self::assertResponseStatusCodeSame(422);
    }

    public function testTheCodeCannotBeGuessedByTrying(): void
    {
        $code = $this->code()->current();
        for ($i = 0; $i < 10; ++$i) {
            $this->api('POST', '/api/setup/verify', ['code' => 'AAAA-AAA'.$i]);
            self::assertResponseStatusCodeSame(403);
        }

        // Even the right code waits now
        $this->api('POST', '/api/setup/verify', ['code' => $code]);
        self::assertResponseStatusCodeSame(429);
    }

    private function code(): SetupCode
    {
        $code = self::getContainer()->get(SetupCode::class);
        $code->clear();

        return $code;
    }

    private function install(string $code): array
    {
        return ['code' => $code, 'clanName' => 'Imperium', 'username' => 'papa', 'displayName' => 'Papa', 'email' => 'papa@example.com', 'password' => 'motdepasse'];
    }
}
