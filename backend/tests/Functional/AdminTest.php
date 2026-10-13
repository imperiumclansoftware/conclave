<?php

namespace App\Tests\Functional;

use App\Entity\Setting;
use App\Settings\AppSettings;
use App\Tests\Support\HttpClientMock;
use Doctrine\ORM\EntityManagerInterface;

final class AdminTest extends ApiTestCase
{
    public function testGeneralSettings(): void
    {
        $leader = $this->registerClan('Martin');

        $settings = $this->api('PUT', '/api/admin/settings/general', ['clanName' => 'Imperium', 'appUrl' => 'https://clan.example/'], $leader['token']);

        self::assertResponseIsSuccessful();
        self::assertSame('Imperium', $settings['general']['clanName']);
        self::assertSame(['value' => 'https://clan.example/', 'source' => 'admin'], $settings['general']['appUrl']);
        self::assertSame('Imperium', $this->api('GET', '/api/setup')['clanName']);
    }

    public function testSmtpServerIsSavedFieldByFieldAndThePasswordNeverLeaves(): void
    {
        $leader = $this->registerClan();
        $smtp = ['mode' => 'smtp', 'host' => 'SMTP.Example.com', 'port' => 587, 'security' => 'starttls', 'username' => 'clan@example.com',
            'password' => 'p@ss:w/rd', 'from' => 'clan@example.com', 'fromName' => 'Les Imperium'];

        $settings = $this->api('PUT', '/api/admin/settings/mail', $smtp, $leader['token']);
        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString('p@ss', $this->client->getResponse()->getContent());
        self::assertTrue($settings['mail']['configured']);
        self::assertSame('admin', $settings['mail']['source']);
        self::assertSame(['mode' => 'smtp', 'host' => 'smtp.example.com', 'port' => 587, 'security' => 'starttls', 'username' => 'clan@example.com',
            'passwordSet' => true, 'verifyPeer' => true, 'customDsn' => null], $settings['mail']['server']);
        self::assertSame('Les Imperium', $settings['mail']['fromName']['value']);
        // Special characters are encoded for the leader
        self::assertSame('smtp://clan%40example.com:p%40ss%3Aw%2Frd@smtp.example.com:587?require_tls=true', $this->savedDsn());

        // An empty password keeps the saved one for the same account…
        $this->api('PUT', '/api/admin/settings/mail', ['password' => null, 'port' => 465, 'security' => 'tls'] + $smtp, $leader['token']);
        self::assertSame('smtps://clan%40example.com:p%40ss%3Aw%2Frd@smtp.example.com:465', $this->savedDsn());
        // …but is never sent to another server
        $settings = $this->api('PUT', '/api/admin/settings/mail', ['password' => null, 'host' => 'smtp.ailleurs.fr'] + $smtp, $leader['token']);
        self::assertFalse($settings['mail']['server']['passwordSet']);

        $settings = $this->api('PUT', '/api/admin/settings/mail', ['mode' => 'disabled'] + $smtp, $leader['token']);
        self::assertSame('disabled', $settings['mail']['server']['mode']);
        self::assertFalse($settings['mail']['configured']);

        $settings = $this->api('DELETE', '/api/admin/settings/mail/server', token: $leader['token']);
        self::assertSame('env', $settings['mail']['source']);
    }

    public function testInvalidMailServerIsRejected(): void
    {
        $leader = $this->registerClan();

        foreach ([['host' => ''], ['host' => 'smtp://smtp.example.com'], ['port' => 70000], ['security' => 'ssl'], ['mode' => 'custom', 'dsn' => 'pas un dsn']] as $invalid) {
            $this->api('PUT', '/api/admin/settings/mail', $invalid + ['mode' => 'smtp', 'host' => 'smtp.example.com', 'from' => 'clan@example.com'], $leader['token']);
            self::assertResponseStatusCodeSame(422, json_encode($invalid));
        }
    }

    public function testMailTestUsesTheValuesOfTheFormWithoutSavingThem(): void
    {
        $leader = $this->registerClan();

        $result = $this->api('POST', '/api/admin/mail/test', ['mode' => 'custom', 'dsn' => 'null://null', 'from' => 'noreply@clan.example',
            'fromName' => 'Les Imperium', 'recipient' => 'ami@example.com'], $leader['token']);
        self::assertTrue($result['ok'], json_encode($result));
        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertEmailAddressContains($email, 'From', 'noreply@clan.example');
        self::assertSame('Les Imperium', $email->getFrom()[0]->getName());
        self::assertEmailAddressContains($email, 'To', 'ami@example.com');

        $settings = $this->api('GET', '/api/admin/settings', token: $leader['token']);
        self::assertNotSame('admin', $settings['mail']['source']);
    }

    public function testMailTestExplainsAnUnreachableServer(): void
    {
        $leader = $this->registerClan();

        $result = $this->api('POST', '/api/admin/mail/test', ['mode' => 'smtp', 'host' => '127.0.0.1', 'port' => 1, 'security' => 'none',
            'from' => 'noreply@clan.example'], $leader['token']);
        self::assertFalse($result['ok']);
        self::assertSame('Connexion à 127.0.0.1:1', $result['checks'][0]['label']);
        self::assertStringStartsWith('Connexion refusée', $result['checks'][0]['detail']);
        self::assertEmailCount(0);
    }

    private function savedDsn(): ?string
    {
        $settings = self::getContainer()->get(AppSettings::class);
        $settings->reset();

        return $settings->get(AppSettings::MAILER_DSN);
    }

    public function testSettingsFallBackToTheEnvironmentAndNeverExposeSecrets(): void
    {
        $leader = $this->registerClan();

        $this->api('GET', '/api/admin/settings', token: $leader['token']);
        $raw = $this->client->getResponse()->getContent();
        $settings = json_decode($raw, true);

        self::assertSame(['value' => '123456789', 'source' => 'env'], $settings['discord']['clientId']);
        self::assertSame(['set' => true, 'hint' => '…oken', 'source' => 'env'], $settings['discord']['botToken']);
        self::assertStringNotContainsString('test-bot-token', $raw);
        self::assertStringNotContainsString('test-secret', $raw);
        self::assertSame('stopped', $settings['discord']['gateway']['state']);
    }

    public function testLeaderSavesDiscordSettingsEncrypted(): void
    {
        $leader = $this->registerClan();

        $settings = $this->api('PUT', '/api/admin/settings/discord', [
            'clientId' => '987654321012345678',
            'clientSecret' => 'new-client-secret',
            'botToken' => 'new.bot-token',
            'redirectUri' => 'https://clan.example/discord/callback',
        ], $leader['token']);

        self::assertResponseIsSuccessful();
        self::assertSame(['value' => '987654321012345678', 'source' => 'admin'], $settings['discord']['clientId']);
        $stored = static::getContainer()->get(EntityManagerInterface::class)->find(Setting::class, 'discord.bot_token')->getValue();
        self::assertStringNotContainsString('bot-token', base64_decode($stored));

        // Omitted secrets are kept; an empty value falls back to the environment
        $settings = $this->api('PUT', '/api/admin/settings/discord', ['clientId' => '', 'redirectUri' => 'https://clan.example/discord/callback'], $leader['token']);
        self::assertSame(['value' => '123456789', 'source' => 'env'], $settings['discord']['clientId']);
        self::assertSame('admin', $settings['discord']['botToken']['source']);
    }

    public function testDiscordConnectionCheck(): void
    {
        $leader = $this->registerClan();

        $result = $this->api('POST', '/api/admin/discord/test', token: $leader['token']);
        self::assertTrue($result['ok']);

        HttpClientMock::$presenceIntent = false;
        $result = $this->api('POST', '/api/admin/discord/test', token: $leader['token']);
        self::assertFalse(array_column($result['checks'], null, 'label')['Presence Intent']['ok']);
    }
}
