<?php

namespace App\Tests\Functional;

use App\Discord\DiscordPermission;
use App\Discord\Gateway\GatewayEventHandler;
use App\Tests\Support\HttpClientMock;

final class VideoCallTest extends ApiTestCase
{
    private array $leader;
    private array $lea;
    private array $tom;

    protected function setUp(): void
    {
        parent::setUp();
        $this->leader = $this->registerClan();
        $this->lea = $this->addMember($this->leader['token'], 'lea');
        $this->tom = $this->addMember($this->leader['token'], 'tom');
        $this->linkDiscord($this->leader['token'], '111', 'install');
        HttpClientMock::$guildMembers['111'] = true;
        $this->linkDiscord($this->lea['token'], '222');
        HttpClientMock::$published = [];
    }

    public function testLeaderStartsAPrivateCallWithChosenMembers(): void
    {
        $call = $this->api('POST', '/api/calls', ['userIds' => [$this->lea['user']['id']], 'title' => 'Raid du soir'], $this->leader['token']);

        self::assertResponseStatusCodeSame(201);
        self::assertSame('Raid du soir', $call['title']);
        self::assertMatchesRegularExpression('#^https://discord.com/channels/900/\d+$#', $call['joinUrl']);
        self::assertEqualsCanonicalizing(['papa', 'lea'], array_column($call['participants'], 'username'));

        // A hidden category, then a voice channel only the participants can see
        $created = HttpClientMock::discordRequests('POST', '#^guilds/900/channels$#');
        self::assertSame([4, 2], array_column(array_column($created, 'body'), 'type'));
        $channel = $created[1]['body'];
        self::assertSame('🎥 Raid du soir', $channel['name']);
        $overwrites = array_column($channel['permission_overwrites'], null, 'id');
        self::assertSame((string) (DiscordPermission::VIEW_CHANNEL | DiscordPermission::CONNECT), $overwrites['900']['deny']);
        self::assertSame((string) DiscordPermission::CALL_PARTICIPANT, $overwrites['111']['allow']);
        self::assertSame((string) DiscordPermission::CALL_PARTICIPANT, $overwrites['222']['allow']);
        self::assertArrayNotHasKey('333', $overwrites);

        // Léa is invited by DM and in the app
        self::assertCount(1, HttpClientMock::discordRequests('POST', '#^channels/dm-222/messages$#'));
        self::assertCount(1, HttpClientMock::publishedOfType('call'));
        self::assertSame([$call['id']], array_column($this->api('GET', '/api/calls', token: $this->lea['token']), 'id'));
        self::assertSame([], $this->api('GET', '/api/calls', token: $this->tom['token']));
    }

    public function testInviteesMustHaveLinkedDiscord(): void
    {
        $result = $this->api('POST', '/api/calls', ['userIds' => [$this->tom['user']['id']]], $this->leader['token']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('Compte Discord non lié : Tom.', $result['detail']);
    }

    public function testInviteesMustBeOnTheServer(): void
    {
        unset(HttpClientMock::$guildMembers['222']);

        $result = $this->api('POST', '/api/calls', ['userIds' => [$this->lea['user']['id']]], $this->leader['token']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('Pas encore sur le serveur Discord du clan : Lea.', $result['detail']);
    }

    public function testPendingAccountsCannotBeInvited(): void
    {
        $pendingId = $this->signUp('marc');

        $this->api('POST', '/api/calls', ['userIds' => [$pendingId]], $this->leader['token']);

        self::assertResponseStatusCodeSame(422);
    }

    public function testCreatorEndsTheCall(): void
    {
        $call = $this->api('POST', '/api/calls', ['userIds' => [$this->lea['user']['id']]], $this->leader['token']);

        $this->api('DELETE', "/api/calls/{$call['id']}", token: $this->tom['token']);
        self::assertResponseStatusCodeSame(403);

        $this->api('DELETE', "/api/calls/{$call['id']}", token: $this->leader['token']);
        self::assertResponseStatusCodeSame(204);
        self::assertCount(1, HttpClientMock::discordRequests('DELETE', '#^channels/\d+$#'));
        self::assertCount(1, HttpClientMock::publishedOfType('call-ended'));
        self::assertSame([], $this->api('GET', '/api/calls', token: $this->lea['token']));
    }

    public function testGatewayTracksPresencesAndEndsEmptyCalls(): void
    {
        $call = $this->api('POST', '/api/calls', ['userIds' => [$this->lea['user']['id']]], $this->leader['token']);
        $channelId = substr($call['joinUrl'], strrpos($call['joinUrl'], '/') + 1);
        $gateway = static::getContainer()->get(GatewayEventHandler::class);

        $gateway->handle('GUILD_CREATE', ['id' => '900', 'presences' => [
            ['user' => ['id' => '111'], 'status' => 'dnd', 'activities' => [['type' => 4, 'state' => 'Concentré'], ['type' => 0, 'name' => 'Elden Ring']]],
        ], 'voice_states' => []]);
        $gateway->handle('PRESENCE_UPDATE', ['guild_id' => '900', 'user' => ['id' => '222'], 'status' => 'idle', 'activities' => []]);

        $presence = array_column($this->api('GET', '/api/clan/presence', token: $this->tom['token']), 'discord', 'userId');
        self::assertSame(['dnd', 'Joue à Elden Ring'], [$presence[$this->leader['user']['id']]['status'], $presence[$this->leader['user']['id']]['activity']]);
        self::assertSame('idle', $presence[$this->lea['user']['id']]['status']);
        self::assertNull($presence[$this->tom['user']['id']]);

        // Léa joins, then the leader, then both leave: the call ends and its channel is deleted
        $gateway->handle('VOICE_STATE_UPDATE', ['guild_id' => '900', 'user_id' => '222', 'channel_id' => $channelId]);
        $gateway->handle('VOICE_STATE_UPDATE', ['guild_id' => '900', 'user_id' => '111', 'channel_id' => $channelId]);
        $ongoing = $this->api('GET', '/api/calls', token: $this->lea['token']);
        self::assertEqualsCanonicalizing([$this->leader['user']['id'], $this->lea['user']['id']], $ongoing[0]['connectedUserIds']);
        self::assertTrue($ongoing[0]['started']);

        $gateway->handle('VOICE_STATE_UPDATE', ['guild_id' => '900', 'user_id' => '222', 'channel_id' => null]);
        $gateway->handle('VOICE_STATE_UPDATE', ['guild_id' => '900', 'user_id' => '111', 'channel_id' => null]);

        self::assertSame([], $this->api('GET', '/api/calls', token: $this->lea['token']));
        self::assertCount(1, HttpClientMock::discordRequests('DELETE', "#^channels/$channelId$#"));
    }
}
