<?php

namespace App\Tests\Functional;

use App\Tests\Support\HttpClientMock;

final class ConversationTest extends ApiTestCase
{
    public function testMessagesSentToAnOfflineMemberAreKeptAsUnread(): void
    {
        $leader = $this->registerClan();
        $member = $this->addMember($leader['token'], 'lea');

        $conversation = $this->api('POST', '/api/conversations', ['userId' => $member['user']['id']], $leader['token']);
        $this->api('POST', "/api/conversations/{$conversation['id']}/messages", ['content' => 'Coucou !'], $leader['token']);
        $last = $this->api('POST', "/api/conversations/{$conversation['id']}/messages", ['content' => 'Tu rentres quand ?'], $leader['token']);
        self::assertResponseStatusCodeSame(201);

        // Léa comes back: both messages are waiting for her
        $conversations = $this->api('GET', '/api/conversations', token: $member['token']);
        self::assertCount(1, $conversations);
        self::assertSame(2, $conversations[0]['unreadCount']);
        self::assertSame('Tu rentres quand ?', $conversations[0]['lastMessage']['content']);

        $messages = $this->api('GET', "/api/conversations/{$conversation['id']}/messages", token: $member['token']);
        self::assertSame(['Coucou !', 'Tu rentres quand ?'], array_column($messages, 'content'));

        // The sender has no unread message
        self::assertSame(0, $this->api('GET', '/api/conversations', token: $leader['token'])[0]['unreadCount']);

        $this->api('POST', "/api/conversations/{$conversation['id']}/read", ['messageId' => $last['id']], $member['token']);
        self::assertResponseStatusCodeSame(204);
        self::assertSame(0, $this->api('GET', '/api/conversations', token: $member['token'])[0]['unreadCount']);
    }

    public function testOpeningTwiceReturnsTheSameConversation(): void
    {
        $leader = $this->registerClan();
        $member = $this->addMember($leader['token'], 'lea');

        $first = $this->api('POST', '/api/conversations', ['userId' => $member['user']['id']], $leader['token']);
        $second = $this->api('POST', '/api/conversations', ['userId' => $leader['user']['id']], $member['token']);

        self::assertSame($first['id'], $second['id']);
    }

    public function testSendingPublishesAPrivateUpdateToEachParticipant(): void
    {
        $leader = $this->registerClan();
        $member = $this->addMember($leader['token'], 'lea');
        $conversation = $this->api('POST', '/api/conversations', ['userId' => $member['user']['id']], $leader['token']);

        HttpClientMock::$published = [];
        $this->api('POST', "/api/conversations/{$conversation['id']}/messages", ['content' => 'Coucou !'], $leader['token']);

        self::assertCount(1, HttpClientMock::$published);
        $update = HttpClientMock::$published[0];
        self::assertSame('on', $update['private']);
        self::assertEqualsCanonicalizing([
            "https://clan-app.local/users/{$leader['user']['id']}",
            "https://clan-app.local/users/{$member['user']['id']}",
        ], $update['topic']);
        self::assertSame('Coucou !', json_decode($update['data'], true)['message']['content']);
    }

    public function testBlankMessagesAreRejected(): void
    {
        $leader = $this->registerClan();
        $member = $this->addMember($leader['token'], 'lea');
        $conversation = $this->api('POST', '/api/conversations', ['userId' => $member['user']['id']], $leader['token']);

        $this->api('POST', "/api/conversations/{$conversation['id']}/messages", ['content' => '   '], $leader['token']);

        self::assertResponseStatusCodeSame(422);
    }

    public function testCannotTalkToPendingAccounts(): void
    {
        $leader = $this->registerClan();
        $pendingId = $this->signUp('marc');

        $this->api('POST', '/api/conversations', ['userId' => $pendingId], $leader['token']);

        self::assertResponseStatusCodeSame(422);
    }

    public function testMessagesArePaginatedBackwards(): void
    {
        $leader = $this->registerClan();
        $member = $this->addMember($leader['token'], 'lea');
        $conversation = $this->api('POST', '/api/conversations', ['userId' => $member['user']['id']], $leader['token']);
        for ($i = 1; $i <= 55; ++$i) {
            $this->api('POST', "/api/conversations/{$conversation['id']}/messages", ['content' => "Message $i"], $leader['token']);
        }

        $latest = $this->api('GET', "/api/conversations/{$conversation['id']}/messages", token: $member['token']);
        self::assertCount(50, $latest);
        self::assertSame('Message 6', $latest[0]['content']);
        self::assertSame('Message 55', $latest[49]['content']);

        $older = $this->api('GET', "/api/conversations/{$conversation['id']}/messages?before={$latest[0]['id']}", token: $member['token']);
        self::assertSame(['Message 1', 'Message 2', 'Message 3', 'Message 4', 'Message 5'], array_column($older, 'content'));
    }

    public function testMercureAuthorizationCookieIsSet(): void
    {
        $leader = $this->registerClan();

        $result = $this->api('POST', '/api/mercure/authorize', token: $leader['token']);

        self::assertResponseIsSuccessful();
        self::assertSame("https://clan-app.local/users/{$leader['user']['id']}", $result['topic']);
        self::assertResponseHasCookie('mercure_access_token', '/.well-known/mercure');
    }
}
