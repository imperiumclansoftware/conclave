<?php

namespace App\Tests\Functional;

use App\Tests\Support\HttpClientMock;

final class EventTest extends ApiTestCase
{
    public function testPlayersOrganiseGameSessionsSeenByTheWholeClan(): void
    {
        $leader = $this->registerClan();
        ['token' => $lea, 'user' => $leaUser] = $this->addMember($leader['token'], 'lea');
        ['token' => $tom] = $this->addMember($leader['token'], 'tom');
        $marc = $this->addMember($leader['token'], 'marc')['token'];
        $this->joinBranch($leader['token'], 'players', $lea, $tom);

        $types = $this->api('GET', '/api/events/types', token: $lea);
        $session = array_values(array_filter($types['types'], static fn (array $t): bool => 'game_session' === $t['type']))[0];
        self::assertSame(['Session de jeu', 'Joueurs', true, [['name' => 'game', 'required' => true], ['name' => 'gamePlatform', 'required' => true]]],
            [$session['label'], $session['branch']['name'], $session['canCreate'], $session['fields']]);
        self::assertNotContains('watch_party', array_column($types['types'], 'type'), 'Inactive branch');

        // Not in the branch: no creation
        $this->api('POST', '/api/events', $this->gameSession(), $marc);
        self::assertResponseStatusCodeSame(403);
        // The specific fields of the type are required
        $this->api('POST', '/api/events', ['gamePlatform' => null] + $this->gameSession(), $lea);
        self::assertResponseStatusCodeSame(422);

        $event = $this->api('POST', '/api/events', $this->gameSession(), $lea);
        self::assertResponseStatusCodeSame(201);
        self::assertSame(['Soirée Elden Ring', 'Session de jeu', 'ELDEN RING', 'PC', true, 'lea', 4],
            [$event['title'], $event['typeLabel'], $event['game']['name'], $event['gamePlatform']['label'], $event['participating'], $event['createdBy']['username'], $event['maxParticipants']]);
        self::assertSame([$leaUser['id']], array_column($event['participants'], 'id'), 'The organiser comes');
        self::assertNotEmpty(HttpClientMock::publishedOfType('events'));

        // Everybody sees it; only the players join
        $seen = $this->api('GET', '/api/events', token: $marc)[0];
        self::assertSame([$event['id'], false, false], [$seen['id'], $seen['canParticipate'], $seen['canEdit']]);
        $this->api('PUT', "/api/events/{$event['id']}/participation", token: $marc);
        self::assertResponseStatusCodeSame(403);
        self::assertCount(2, $this->api('PUT', "/api/events/{$event['id']}/participation", token: $tom)['participants']);

        // Places are limited
        $this->api('PUT', "/api/events/{$event['id']}", ['maxParticipants' => 2] + $this->gameSession(), $lea);
        $this->joinBranch($leader['token'], 'players', $leader['token']);
        $this->api('PUT', "/api/events/{$event['id']}/participation", token: $leader['token']);
        self::assertResponseStatusCodeSame(409);
        self::assertSame([$leaUser['id']], array_column($this->api('DELETE', "/api/events/{$event['id']}/participation", token: $tom)['participants'], 'id'));

        // The organiser edits, the others cannot; leaders moderate
        $this->api('PUT', "/api/events/{$event['id']}", ['title' => 'Piraté'] + $this->gameSession(), $tom);
        self::assertResponseStatusCodeSame(403);
        $edited = $this->api('PUT', "/api/events/{$event['id']}", ['title' => 'Raid du vendredi'] + $this->gameSession(), $lea);
        self::assertSame('Raid du vendredi', $edited['title']);
        $this->api('DELETE', "/api/events/{$event['id']}", token: $leader['token']);
        self::assertResponseStatusCodeSame(204);
        self::assertSame([], $this->api('GET', '/api/events', token: $marc));
    }

    public function testCinephilesOrganiseWatchPartiesWithTmdbFilmsAndSeries(): void
    {
        $leader = $this->registerClan();
        $this->api('PUT', '/api/admin/settings/tmdb', ['apiKey' => HttpClientMock::TMDB_KEY], $leader['token']);
        ['token' => $lea] = $this->addMember($leader['token'], 'lea');
        $marc = $this->addMember($leader['token'], 'marc')['token'];
        $this->joinBranch($leader['token'], 'cinephiles', $lea);

        $event = $this->api('POST', '/api/events', [
            'type' => 'watch_party', 'title' => 'Dune en VO', 'startsAt' => '2030-06-01T21:00:00+02:00',
            'tmdbId' => 438631, 'filmPlatform' => 'netflix',
            // Fields of other types are ignored
            'gamePlatform' => 'pc',
        ], $lea);
        self::assertResponseStatusCodeSame(201);
        self::assertSame([['kind' => 'movie', 'tmdbId' => 438631, 'title' => 'Dune', 'year' => '2021', 'posterUrl' => 'https://image.tmdb.org/t/p/w342/dune.jpg'], 'Netflix', null],
            [$event['film'], $event['filmPlatform']['label'], $event['gamePlatform']]);
        self::assertSame('2030-06-01T19:00:00+00:00', (new \DateTimeImmutable($event['startsAt']))->setTimezone(new \DateTimeZone('UTC'))->format(\DATE_ATOM), 'Same instant');

        // The others see the film of the event, but not its fiche
        self::assertSame('Dune', $this->api('GET', "/api/events/{$event['id']}", token: $marc)['film']['title']);
        $this->api('GET', '/api/films/movie/438631', token: $marc);
        self::assertResponseStatusCodeSame(403);

        // A series, for a ciné-club
        $club = $this->api('POST', '/api/events', [
            'type' => 'cine_club', 'title' => 'Prophecy, épisode 1', 'startsAt' => '2030-06-08T21:00:00+02:00',
            'tmdbId' => 90228, 'filmKind' => 'tv',
        ], $lea);
        self::assertResponseStatusCodeSame(201);
        self::assertSame(['tv', 90228, 'Dune : Prophecy'], [$club['film']['kind'], $club['film']['tmdbId'], $club['film']['title']]);
        $this->api('POST', '/api/events', ['type' => 'cine_club', 'title' => 'Pas une série', 'startsAt' => '2030-06-08T21:00:00+02:00', 'tmdbId' => 438631, 'filmKind' => 'tv'], $lea);
        self::assertResponseStatusCodeSame(422);

        $this->api('POST', '/api/events', ['type' => 'watch_party', 'title' => 'Sans film', 'startsAt' => '2030-06-01T21:00:00+02:00', 'filmPlatform' => 'netflix'], $lea);
        self::assertResponseStatusCodeSame(422);
        $this->api('POST', '/api/events', ['type' => 'cine_club', 'title' => 'Inconnu', 'startsAt' => '2030-06-01T21:00:00+02:00', 'tmdbId' => 999999], $lea);
        self::assertResponseStatusCodeSame(422);
    }

    public function testPastEventsAndDeactivatedBranches(): void
    {
        $leader = $this->registerClan();
        $players = $this->joinBranch($leader['token'], 'players', $leader['token']);
        $this->api('POST', '/api/events', ['startsAt' => '2020-01-01T20:00:00+00:00', 'endsAt' => '2020-01-01T23:00:00+00:00'] + $this->gameSession(), $leader['token']);
        self::assertResponseStatusCodeSame(201);

        self::assertSame([], $this->api('GET', '/api/events', token: $leader['token']));
        $past = $this->api('GET', '/api/events?period=past', token: $leader['token']);
        self::assertSame([true, false], [$past[0]['over'], $past[0]['canParticipate']]);

        $this->api('POST', '/api/events', ['endsAt' => '2020-01-01T19:00:00+00:00'] + $this->gameSession(), $leader['token']);
        self::assertResponseStatusCodeSame(422, 'The end comes after the start');

        $this->api('PUT', "/api/admin/branches/$players/active", ['active' => false], $leader['token']);
        self::assertSame([], $this->api('GET', '/api/events?period=past', token: $leader['token']));
        $this->api('GET', "/api/events/{$past[0]['id']}", token: $leader['token']);
        self::assertResponseStatusCodeSame(404);
    }

    public function testThePlanningListsTheEventsOfAPeriod(): void
    {
        $leader = $this->registerClan();
        $this->joinBranch($leader['token'], 'players', $leader['token']);
        foreach (['2030-05-30T21:00:00+02:00', '2030-06-02T21:00:00+02:00', '2030-07-14T21:00:00+02:00'] as $i => $start) {
            $this->api('POST', '/api/events', ['title' => "Soirée $i", 'startsAt' => $start, 'endsAt' => null] + $this->gameSession(), $leader['token']);
        }
        // Over several days: shown on each of them
        $this->api('POST', '/api/events', ['title' => 'Marathon', 'startsAt' => '2030-05-31T20:00:00+02:00', 'endsAt' => '2030-06-01T04:00:00+02:00'] + $this->gameSession(), $leader['token']);

        $june = $this->api('GET', '/api/events?from=2030-06-01T00:00:00%2B02:00&to=2030-07-01T00:00:00%2B02:00', token: $leader['token']);
        self::assertSame(['Marathon', 'Soirée 1'], array_column($june, 'title'));

        $this->api('GET', '/api/events?from=2030-01-01T00:00:00Z&to=2030-12-31T00:00:00Z', token: $leader['token']);
        self::assertResponseStatusCodeSame(400, 'At most 45 days');
        $this->api('GET', '/api/events?from=demain&to=2030-06-01T00:00:00Z', token: $leader['token']);
        self::assertResponseStatusCodeSame(400);
    }

    /** @return array<string, mixed> */
    private function gameSession(): array
    {
        return [
            'type' => 'game_session',
            'title' => 'Soirée Elden Ring',
            'description' => 'Boss de fin, venez nombreux.',
            'startsAt' => '2030-05-30T21:00:00+02:00',
            'endsAt' => '2030-05-30T23:30:00+02:00',
            'maxParticipants' => 4,
            'gameSource' => 'steam',
            'gameExternalId' => '1245620',
            'gamePlatform' => 'pc',
        ];
    }
}
