<?php

namespace App\Tests\Functional;

use App\Tests\Support\HttpClientMock;

final class GameEventInvitationTest extends ApiTestCase
{
    public function testThePlayersOfTheGameOnItsPlatformAreInvited(): void
    {
        $leader = $this->registerClan();
        $members = [];
        foreach (['lea', 'marc', 'zoe', 'tom'] as $name) {
            $members[$name] = $this->addMember($leader['token'], $name);
        }
        $players = $this->joinBranch($leader['token'], 'players', $leader['token'], $members['lea']['token'], $members['marc']['token'], $members['zoe']['token'], $members['tom']['token']);
        $this->own($leader['token'], ['pc']);
        $this->own($members['lea']['token'], ['pc', 'ps5']);
        $this->own($members['marc']['token'], ['ps5']);
        $this->own($members['zoe']['token'], ['pc']);
        // Tom has the game but left the Joueurs branch: he cannot join
        $this->own($members['tom']['token'], ['pc']);
        $this->api('DELETE', "/api/clan/branches/$players/membership", token: $members['tom']['token']);
        self::assertResponseIsSuccessful();

        $me = $this->api('PUT', '/api/me/notifications', ['gameEventEmails' => false], $members['zoe']['token']);
        self::assertSame(['gameEventEmails' => false], $me['notifications']);
        self::assertSame(['gameEventEmails' => true], $this->api('GET', '/api/me', token: $members['lea']['token'])['notifications']);

        HttpClientMock::reset();
        $event = $this->api('POST', '/api/events', $this->gameSession('2030-05-30T21:00:00+02:00'), $leader['token']);
        self::assertResponseStatusCodeSame(201);

        // In the browser: Léa and Zoé (the organiser and the PS5 player are left out)
        $invitations = HttpClientMock::publishedOfType('event-invitation');
        self::assertCount(1, $invitations);
        $topics = array_map(static fn (string $t): string => basename($t), (array) $invitations[0]['_topics']);
        sort($topics);
        $expected = [(string) $members['lea']['user']['id'], (string) $members['zoe']['user']['id']];
        sort($expected);
        self::assertSame($expected, $topics);
        self::assertSame(['Soirée Elden Ring', 'ELDEN RING', 'PC', 'Papa'],
            [$invitations[0]['event']['title'], $invitations[0]['event']['game'], $invitations[0]['event']['platform'], $invitations[0]['event']['organiser']]);
        self::assertSame($event['id'], $invitations[0]['event']['id']);

        // By e-mail: Léa only, Zoé turned them off; in the clan's time zone
        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertEmailAddressContains($email, 'To', 'lea@example.com');
        self::assertEmailHeaderSame($email, 'Subject', '[Martin] Session de jeu sur ELDEN RING : Soirée Elden Ring');
        self::assertEmailHtmlBodyContains($email, 'jeudi 30 mai 2030 à 21:00');
        self::assertEmailHtmlBodyContains($email, 'sur PC');
    }

    public function testNoInvitationForAPastEventOrWithoutPlayers(): void
    {
        $leader = $this->registerClan();
        ['token' => $lea] = $this->addMember($leader['token'], 'lea');
        $this->joinBranch($leader['token'], 'players', $leader['token'], $lea);
        $this->own($lea, ['pc']);

        HttpClientMock::reset();
        $this->api('POST', '/api/events', $this->gameSession('2020-05-30T21:00:00+02:00'), $leader['token']);
        self::assertResponseStatusCodeSame(201);
        self::assertSame([], HttpClientMock::publishedOfType('event-invitation'));
        self::assertEmailCount(0);

        $this->api('POST', '/api/events', ['gamePlatform' => 'switch'] + $this->gameSession('2030-05-30T21:00:00+02:00'), $leader['token']);
        self::assertResponseStatusCodeSame(201);
        self::assertSame([], HttpClientMock::publishedOfType('event-invitation'), 'Nobody plays it on Switch');
    }

    /** @param list<string> $platforms */
    private function own(string $token, array $platforms): void
    {
        $this->api('POST', '/api/me/games', ['source' => 'steam', 'externalId' => '1245620', 'platforms' => $platforms], $token);
        self::assertResponseIsSuccessful();
    }

    /** @return array<string, mixed> */
    private function gameSession(string $startsAt): array
    {
        return [
            'type' => 'game_session',
            'title' => 'Soirée Elden Ring',
            'startsAt' => $startsAt,
            'gameSource' => 'steam',
            'gameExternalId' => '1245620',
            'gamePlatform' => 'pc',
        ];
    }
}
