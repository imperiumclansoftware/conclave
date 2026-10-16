<?php

namespace App\Tests\Functional;

final class EventChatTest extends ApiTestCase
{
    public function testParticipantsTalkBeforeAndDuringTheEventAndTheClanReads(): void
    {
        $leader = $this->registerClan();
        ['token' => $lea] = $this->addMember($leader['token'], 'lea');
        ['token' => $marc] = $this->addMember($leader['token'], 'marc');
        $this->joinBranch($leader['token'], 'players', $leader['token'], $lea);
        $event = $this->api('POST', '/api/events', $this->gameSession('2030-05-30T21:00:00+02:00', '2030-05-30T23:30:00+02:00'), $leader['token']);
        $id = $event['id'];
        self::assertSame(['open' => true, 'closesAt' => '2030-05-30T23:30:00+02:00', 'canWrite' => true], $this->normalizeChat($event['chat']));

        $posted = $this->api('POST', "/api/events/$id/messages", ['content' => "  On se retrouve sur Discord à 20 h 45.\n"], $leader['token']);
        self::assertResponseStatusCodeSame(201);
        self::assertSame(['On se retrouve sur Discord à 20 h 45.', 'papa', $id], [$posted['content'], $posted['author']['username'], $posted['eventId']]);

        // Everybody reads; only the participants write
        $page = $this->api('GET', "/api/events/$id/messages", token: $marc);
        self::assertSame([['On se retrouve sur Discord à 20 h 45.'], false, false], [array_column($page['items'], 'content'), $page['hasMore'], $page['canModerate']]);
        self::assertFalse($this->api('GET', "/api/events/$id", token: $marc)['chat']['canWrite']);
        $this->api('POST', "/api/events/$id/messages", ['content' => 'Je peux venir ?'], $marc);
        self::assertResponseStatusCodeSame(403);
        $this->api('POST', "/api/events/$id/messages", ['content' => 'Pas encore inscrite'], $lea);
        self::assertResponseStatusCodeSame(403);

        $this->api('PUT', "/api/events/$id/participation", token: $lea);
        $mine = $this->api('POST', "/api/events/$id/messages", ['content' => 'Je ramène les potions.'], $lea);
        self::assertResponseStatusCodeSame(201);
        $this->api('POST', "/api/events/$id/messages", ['content' => '   '], $lea);
        self::assertResponseStatusCodeSame(422);

        // The author removes their message, not the others'
        $this->api('DELETE', "/api/events/$id/messages/{$posted['id']}", token: $lea);
        self::assertResponseStatusCodeSame(403);
        $this->api('DELETE', "/api/events/$id/messages/{$mine['id']}", token: $lea);
        self::assertResponseStatusCodeSame(204);
        self::assertCount(1, $this->api('GET', "/api/events/$id/messages", token: $lea)['items']);
    }

    public function testTheChatIsReadOnlyOnceTheEventIsOver(): void
    {
        $leader = $this->registerClan();
        ['token' => $lea] = $this->addMember($leader['token'], 'lea');
        $this->joinBranch($leader['token'], 'players', $leader['token'], $lea);
        $id = $this->api('POST', '/api/events', $this->gameSession('2030-05-30T21:00:00+02:00', null), $leader['token'])['id'];
        $this->api('PUT', "/api/events/$id/participation", token: $lea);
        $message = $this->api('POST', "/api/events/$id/messages", ['content' => 'GG à tous !'], $lea);

        // The event took place (moved to the past by its organiser)
        $this->api('PUT', "/api/events/$id", $this->gameSession('2020-05-30T21:00:00+02:00', '2020-05-30T23:00:00+02:00'), $leader['token']);
        $chat = $this->api('GET', "/api/events/$id", token: $lea)['chat'];
        self::assertSame([false, false], [$chat['open'], $chat['canWrite']]);
        $this->api('POST', "/api/events/$id/messages", ['content' => 'Trop tard'], $lea);
        self::assertResponseStatusCodeSame(403);
        self::assertSame("L'événement est terminé : sa discussion est en lecture seule.", $this->lastError());
        self::assertSame(['GG à tous !'], array_column($this->api('GET', "/api/events/$id/messages", token: $lea)['items'], 'content'), 'Still readable');

        // Only leaders still moderate
        $this->api('DELETE', "/api/events/$id/messages/{$message['id']}", token: $lea);
        self::assertResponseStatusCodeSame(403);
        $this->api('DELETE', "/api/events/$id/messages/{$message['id']}", token: $leader['token']);
        self::assertResponseStatusCodeSame(204);
    }

    public function testWithoutAnEndTheChatStaysOpenForAFewHours(): void
    {
        $leader = $this->registerClan();
        $this->joinBranch($leader['token'], 'players', $leader['token']);

        $started = $this->api('POST', '/api/events', $this->gameSession((new \DateTimeImmutable('-2 hours'))->format(\DATE_ATOM), null), $leader['token']);
        self::assertTrue($started['over'], 'Started, without an end');
        self::assertTrue($started['chat']['open'], 'But its chat goes on');
        $this->api('POST', "/api/events/{$started['id']}/messages", ['content' => 'On continue ?'], $leader['token']);
        self::assertResponseStatusCodeSame(201);

        $old = $this->api('POST', '/api/events', $this->gameSession((new \DateTimeImmutable('-5 hours'))->format(\DATE_ATOM), null), $leader['token']);
        self::assertFalse($old['chat']['open']);
    }

    /** @return array<string, mixed> */
    private function gameSession(string $startsAt, ?string $endsAt): array
    {
        return [
            'type' => 'game_session',
            'title' => 'Soirée Elden Ring',
            'startsAt' => $startsAt,
            'endsAt' => $endsAt,
            'gameSource' => 'steam',
            'gameExternalId' => '1245620',
            'gamePlatform' => 'pc',
        ];
    }

    /**
     * Same instant, written in the event's offset.
     *
     * @param array{open: bool, closesAt: string, canWrite: bool} $chat
     *
     * @return array{open: bool, closesAt: string, canWrite: bool}
     */
    private function normalizeChat(array $chat): array
    {
        $chat['closesAt'] = (new \DateTimeImmutable($chat['closesAt']))->setTimezone(new \DateTimeZone('+02:00'))->format(\DATE_ATOM);

        return $chat;
    }

    private function lastError(): string
    {
        return json_decode((string) $this->client->getResponse()->getContent(), true)['detail'];
    }
}
