<?php

namespace App\Tests\Functional;

use App\Entity\User;
use App\Tests\Support\HttpClientMock;
use Doctrine\ORM\EntityManagerInterface;

final class PresenceTest extends ApiTestCase
{
    public function testHeartbeatsSetTheAppStatusAndNotifyTheClan(): void
    {
        $leader = $this->registerClan();
        $member = $this->addMember($leader['token'], 'lea');

        $this->api('POST', '/api/presence', ['state' => 'online'], $member['token']);
        self::assertResponseStatusCodeSame(204);
        self::assertSame('online', $this->appStatusOf($leader['token'], $member['user']['id']));

        $events = HttpClientMock::publishedOfType('presence');
        self::assertCount(1, $events);
        self::assertSame($member['user']['id'], $events[0]['presence']['userId']);
        // Sent to every clan member
        self::assertCount(2, $events[0]['_topics']);

        // Same status: nothing to broadcast
        $this->api('POST', '/api/presence', ['state' => 'online'], $member['token']);
        self::assertCount(1, HttpClientMock::publishedOfType('presence'));

        $this->api('POST', '/api/presence', ['state' => 'away'], $member['token']);
        self::assertSame('away', $this->appStatusOf($leader['token'], $member['user']['id']));

        $this->api('POST', '/api/presence', ['state' => 'offline'], $member['token']);
        self::assertSame('offline', $this->appStatusOf($leader['token'], $member['user']['id']));
        self::assertCount(3, HttpClientMock::publishedOfType('presence'));
    }

    public function testMembersWithoutRecentHeartbeatAreOffline(): void
    {
        $leader = $this->registerClan();
        $this->api('POST', '/api/presence', ['state' => 'online'], $leader['token']);

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->find(User::class, $leader['user']['id'])->seen(false, new \DateTimeImmutable('-2 minutes'));
        $em->flush();

        self::assertSame('offline', $this->appStatusOf($leader['token'], $leader['user']['id']));
    }

    public function testPendingAccountsAreNotListed(): void
    {
        $leader = $this->registerClan();
        $this->signUp('marc');

        $presence = $this->api('GET', '/api/clan/presence', token: $leader['token']);

        self::assertSame([$leader['user']['id']], array_column($presence, 'userId'));
        self::assertNull($presence[0]['discord']);
    }

    private function appStatusOf(string $token, int $userId): string
    {
        foreach ($this->api('GET', '/api/clan/presence', token: $token) as $presence) {
            if ($presence['userId'] === $userId) {
                return $presence['app']['status'];
            }
        }
        self::fail('Unknown user');
    }
}
