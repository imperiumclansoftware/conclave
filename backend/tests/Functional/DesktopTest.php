<?php

namespace App\Tests\Functional;

final class DesktopTest extends ApiTestCase
{
    private string $downloads;

    protected function setUp(): void
    {
        parent::setUp();
        $this->downloads = static::getContainer()->getParameter('kernel.project_dir').'/var/downloads';
        $this->clean();
    }

    protected function tearDown(): void
    {
        $this->clean();
        parent::tearDown();
    }

    public function testTheApplicationLearnsItsVersionBeforeSigningIn(): void
    {
        $release = $this->api('GET', '/api/desktop/windows');

        self::assertResponseIsSuccessful();
        self::assertSame(['dev', 'http://localhost/api/desktop/windows/download'], [$release['version'], $release['downloadUrl']]);

        // A development server points to the latest release
        $this->client->request('GET', '/api/desktop/windows/download');
        self::assertResponseRedirects('https://github.com/imperiumclansoftware/conclave/releases/latest');
    }

    public function testAnInstallerDroppedOnTheServerIsServed(): void
    {
        if (!is_dir($this->downloads)) {
            mkdir($this->downloads, 0o777, true);
        }
        file_put_contents($this->downloads.'/Conclave-Setup-0.4.0.exe', 'MZ old');
        file_put_contents($this->downloads.'/Conclave-Setup-0.10.0.exe', 'MZ new');

        self::assertSame('0.10.0', $this->api('GET', '/api/desktop/windows')['version']);

        $this->client->request('GET', '/api/desktop/windows/download');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('attachment; filename=Conclave-Setup-0.10.0.exe', (string) $this->client->getResponse()->headers->get('Content-Disposition'));
    }

    private function clean(): void
    {
        foreach (glob($this->downloads.'/Conclave-Setup-*.exe') ?: [] as $file) {
            unlink($file);
        }
    }
}
