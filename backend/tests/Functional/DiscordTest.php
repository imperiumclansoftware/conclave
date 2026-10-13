<?php

namespace App\Tests\Functional;

use App\Tests\Support\HttpClientMock;

final class DiscordTest extends ApiTestCase
{
    public function testStatusBeforeLinking(): void
    {
        $leader = $this->registerClan();

        $status = $this->api('GET', '/api/discord', token: $leader['token']);

        self::assertTrue($status['enabled']);
        self::assertNull($status['account']);
        self::assertNull($status['guild']);
    }

    public function testAuthorizeUrlCarriesAStateAndTheScopes(): void
    {
        $leader = $this->registerClan();

        $link = $this->api('POST', '/api/discord/authorize', ['purpose' => 'link'], $leader['token']);
        parse_str((string) parse_url($link['url'], \PHP_URL_QUERY), $query);
        self::assertStringStartsWith('https://discord.com/oauth2/authorize?', $link['url']);
        self::assertSame(['123456789', 'identify guilds.join', 'https://app.test/discord/callback'], [$query['client_id'], $query['scope'], $query['redirect_uri']]);
        self::assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $query['state']);

        $install = $this->api('POST', '/api/discord/authorize', ['purpose' => 'install'], $leader['token']);
        parse_str((string) parse_url($install['url'], \PHP_URL_QUERY), $query);
        self::assertSame('bot identify guilds.join', $query['scope']);
        self::assertArrayHasKey('permissions', $query);
    }

    public function testOnlyLeadersCanConnectTheServer(): void
    {
        $leader = $this->registerClan();
        $member = $this->addMember($leader['token'], 'lea');

        $this->api('POST', '/api/discord/authorize', ['purpose' => 'install'], $member['token']);

        self::assertResponseStatusCodeSame(403);
    }

    public function testLeaderConnectsTheServerAndMembersJoinIt(): void
    {
        $leader = $this->registerClan();
        $member = $this->addMember($leader['token'], 'lea');

        self::assertSame(['purpose' => 'install'], $this->linkDiscord($leader['token'], '111', 'install'));
        $status = $this->api('GET', '/api/discord', token: $leader['token']);
        self::assertSame(['id' => '900', 'name' => 'Serveur du clan'], $status['guild']);
        self::assertSame('Discord 111', $status['account']['username']);

        $this->linkDiscord($member['token'], '222');
        self::assertResponseIsSuccessful();
        // The member was added to the clan's server with their OAuth token
        $join = HttpClientMock::discordRequests('PUT', '#^guilds/900/members/222$#');
        self::assertSame('token-222', $join[0]['body']['access_token']);
        self::assertTrue($this->api('GET', '/api/discord', token: $member['token'])['account']['onServer']);
    }

    public function testStateIsSingleUseAndBoundToTheUser(): void
    {
        $leader = $this->registerClan();
        $member = $this->addMember($leader['token'], 'lea');
        $authorize = $this->api('POST', '/api/discord/authorize', ['purpose' => 'link'], $leader['token']);
        parse_str((string) parse_url($authorize['url'], \PHP_URL_QUERY), $query);

        $this->api('POST', '/api/discord/callback', ['code' => 'code-111', 'state' => $query['state']], $member['token']);
        self::assertResponseStatusCodeSame(400);

        $this->api('POST', '/api/discord/callback', ['code' => 'code-111', 'state' => $query['state']], $leader['token']);
        // Consumed by the previous attempt
        self::assertResponseStatusCodeSame(400);
    }

    public function testADiscordAccountCanOnlyBeLinkedOnce(): void
    {
        $leader = $this->registerClan();
        $member = $this->addMember($leader['token'], 'lea');
        $this->linkDiscord($leader['token'], '111');

        $this->linkDiscord($member['token'], '111');

        self::assertResponseStatusCodeSame(409);
    }

    public function testUnlink(): void
    {
        $leader = $this->registerClan();
        $this->linkDiscord($leader['token'], '111');

        $this->api('DELETE', '/api/discord/account', token: $leader['token']);

        self::assertResponseStatusCodeSame(204);
        self::assertNull($this->api('GET', '/api/discord', token: $leader['token'])['account']);
    }
}
