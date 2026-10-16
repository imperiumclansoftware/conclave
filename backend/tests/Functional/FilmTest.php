<?php

namespace App\Tests\Functional;

use App\Tests\Support\HttpClientMock;

final class FilmTest extends ApiTestCase
{
    public function testLeadersSaveAndCheckTheTmdbKey(): void
    {
        $leader = $this->registerClan();

        $this->api('PUT', '/api/admin/settings/tmdb', ['apiKey' => 'pas une clé'], $leader['token']);
        self::assertResponseStatusCodeSame(422);
        self::assertFalse($this->api('POST', '/api/admin/tmdb/test', token: $leader['token'])['ok'], 'No key yet');

        $settings = $this->api('PUT', '/api/admin/settings/tmdb', ['apiKey' => HttpClientMock::TMDB_KEY], $leader['token']);
        self::assertSame(['set' => true, 'hint' => '…cdef', 'source' => 'admin'], $settings['tmdb']['apiKey']);
        self::assertStringNotContainsString(HttpClientMock::TMDB_KEY, (string) $this->client->getResponse()->getContent());
        self::assertTrue($this->api('POST', '/api/admin/tmdb/test', token: $leader['token'])['ok']);

        $this->api('PUT', '/api/admin/settings/tmdb', ['apiKey' => 'ffffffffffffffffffffffffffffffff'], $leader['token']);
        $check = $this->api('POST', '/api/admin/tmdb/test', token: $leader['token']);
        self::assertSame([false, 'Clé refusée par TMDB.'], [$check['ok'], $check['checks'][0]['detail']]);
    }

    public function testOnlyCinephilesSearchFilmsAndSeriesAndReadTheirFiches(): void
    {
        $leader = $this->registerClan();
        $this->api('PUT', '/api/admin/settings/tmdb', ['apiKey' => HttpClientMock::TMDB_KEY], $leader['token']);

        $this->api('GET', '/api/films/search?q=dune', token: $leader['token']);
        self::assertResponseStatusCodeSame(403);

        self::assertFalse($this->api('GET', '/api/me', token: $leader['token'])['canAccessFilms']);
        $this->joinBranch($leader['token'], 'cinephiles', $leader['token']);
        self::assertTrue($this->api('GET', '/api/me', token: $leader['token'])['canAccessFilms']);
        $results = $this->api('GET', '/api/films/search?q=dune', token: $leader['token']);
        self::assertSame([
            ['kind' => 'movie', 'tmdbId' => 438631, 'title' => 'Dune', 'originalTitle' => null, 'year' => '2021', 'posterUrl' => 'https://image.tmdb.org/t/p/w185/dune.jpg'],
            ['kind' => 'tv', 'tmdbId' => 90228, 'title' => 'Dune : Prophecy', 'originalTitle' => 'Dune: Prophecy', 'year' => '2024', 'posterUrl' => 'https://image.tmdb.org/t/p/w185/prophecy.jpg'],
            ['kind' => 'movie', 'tmdbId' => 693134, 'title' => 'Dune : Deuxième partie', 'originalTitle' => 'Dune: Part Two', 'year' => '2024', 'posterUrl' => null],
        ], $results, 'Films and series, without the people');
        self::assertSame([], $this->api('GET', '/api/films/search?q=d', token: $leader['token']));

        $film = $this->api('GET', '/api/films/movie/438631', token: $leader['token']);
        self::assertSame(['movie', 'Dune', '2021', 155, ['Science-Fiction', 'Aventure'], ['Denis Villeneuve'], 'Timothée Chalamet', null],
            [$film['kind'], $film['title'], $film['year'], $film['runtime'], $film['genres'], $film['directors'], $film['cast'][0]['name'], $film['seasonCount']]);
        self::assertSame('https://www.themoviedb.org/movie/438631', $film['tmdbUrl']);

        $series = $this->api('GET', '/api/films/tv/90228', token: $leader['token']);
        self::assertSame(['tv', 'Dune : Prophecy', 'Dune: Prophecy', '2024', 63, ['Diane Ademu-John', 'Alison Schapker'], 1, 6, true, null],
            [$series['kind'], $series['title'], $series['originalTitle'], $series['year'], $series['runtime'], $series['directors'],
                $series['seasonCount'], $series['episodeCount'], $series['ongoing'], $series['endYear']]);
        self::assertSame('https://www.themoviedb.org/tv/90228', $series['tmdbUrl']);

        // TMDB numbers films and series separately
        $this->api('GET', '/api/films/tv/438631', token: $leader['token']);
        self::assertResponseStatusCodeSame(404);
        self::assertSame('Cette série est introuvable sur TMDB.', json_decode((string) $this->client->getResponse()->getContent(), true)['detail']);
        $this->api('GET', '/api/films/movie/999999', token: $leader['token']);
        self::assertResponseStatusCodeSame(404);

        // Without a key, the search says so
        $this->api('PUT', '/api/admin/settings/tmdb', ['apiKey' => ''], $leader['token']);
        $this->api('GET', '/api/films/search?q=dune', token: $leader['token']);
        self::assertResponseStatusCodeSame(503);
    }
}
