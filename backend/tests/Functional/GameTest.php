<?php

namespace App\Tests\Functional;

use App\Tests\Support\HttpClientMock;

final class GameTest extends ApiTestCase
{
    /** Games belong to the Joueurs branch: the accounts of these tests join it */
    private ?string $leaderToken = null;

    protected function registerClan(string $clanName = 'Martin', string $username = 'papa'): array
    {
        $leader = parent::registerClan($clanName, $username);
        $this->leaderToken = $leader['token'];
        $this->joinBranch($leader['token'], 'players', $leader['token']);

        return $leader;
    }

    protected function addMember(string $leaderToken, string $username, string $role = 'member'): array
    {
        $member = parent::addMember($leaderToken, $username, $role);
        $this->joinBranch($leaderToken, 'players', $member['token']);

        return $member;
    }

    public function testOnlyTheJoueursBranchSeesTheGames(): void
    {
        $leader = parent::registerClan();
        ['token' => $lea, 'user' => $leaUser] = parent::addMember($leader['token'], 'lea');

        // Nobody, not even a leader, outside of the branch
        self::assertFalse($this->api('GET', '/api/me', token: $leader['token'])['canAccessGames']);
        foreach (['/api/me/games', '/api/clan/games', '/api/games/platforms', '/api/games/search?q=elden'] as $uri) {
            $this->api('GET', $uri, token: $leader['token']);
            self::assertResponseStatusCodeSame(403, $uri);
        }
        $result = $this->addElden($leader['token'], ['pc']);
        self::assertResponseStatusCodeSame(403);
        self::assertSame('Les jeux sont réservés aux membres de la branche Joueurs.', $result['detail']);

        $players = $this->joinBranch($leader['token'], 'players', $leader['token'], $lea);
        self::assertTrue($this->api('GET', '/api/me', token: $lea)['canAccessGames']);
        $this->addElden($lea, ['pc']);
        self::assertResponseStatusCodeSame(201);
        self::assertCount(1, $this->api('GET', "/api/members/{$leaUser['id']}", token: $leader['token'])['games']);
        self::assertSame(1, $this->api('GET', "/api/members/{$leaUser['id']}/card", token: $leader['token'])['stats']['games']);

        // Leaving the branch hides the games again, on the profiles and cards too
        $this->api('DELETE', "/api/clan/branches/$players/membership", token: $leader['token']);
        self::assertNull($this->api('GET', "/api/members/{$leaUser['id']}", token: $leader['token'])['games']);
        self::assertNull($this->api('GET', "/api/members/{$leaUser['id']}/card", token: $leader['token'])['stats']['games']);
        // A deactivated branch gives no access
        $this->api('PUT', "/api/admin/branches/$players/active", ['active' => false], $leader['token']);
        $this->api('GET', '/api/me/games', token: $lea);
        self::assertResponseStatusCodeSame(403);
    }

    public function testSearchListsCatalogGames(): void
    {
        $leader = $this->registerClan();

        $results = $this->api('GET', '/api/games/search?q=elden', token: $leader['token']);

        self::assertResponseIsSuccessful();
        // Bundles and soundtracks are left out
        self::assertSame(['ELDEN RING', 'ELDEN RING NIGHTREIGN'], array_column($results, 'name'));
        self::assertSame(['steam', '1245620', ['pc'], false], [$results[0]['source'], $results[0]['externalId'], $results[0]['platforms'], $results[0]['inLibrary']]);
    }

    public function testShortQueriesReturnNothing(): void
    {
        $leader = $this->registerClan();

        self::assertSame([], $this->api('GET', '/api/games/search?q=e', token: $leader['token']));
    }

    public function testMemberAddsAGameWithPlatforms(): void
    {
        $leader = $this->registerClan();

        $added = $this->addElden($leader['token'], ['ps5', 'pc']);

        self::assertResponseStatusCodeSame(201);
        self::assertSame('ELDEN RING', $added['game']['name']);
        // Stored in the canonical order
        self::assertSame(['pc', 'ps5'], $added['platforms']);

        $details = $this->api('GET', "/api/games/{$added['game']['id']}", token: $leader['token']);
        self::assertSame("LE RPG D'ACTION FANTASTIQUE acclamé.", $details['description']);
        self::assertCount(2, $details['screenshots']);
        self::assertSame(['Action', 'RPG'], $details['genres']);
        self::assertSame(['pc', 'ps5'], $details['mine']['platforms']);

        $search = $this->api('GET', '/api/games/search?q=elden', token: $leader['token']);
        self::assertTrue($search[0]['inLibrary']);
    }

    public function testAGameCanOnlyBeAddedOnce(): void
    {
        $leader = $this->registerClan();
        $this->addElden($leader['token'], ['pc']);

        $this->addElden($leader['token'], ['ps5']);

        self::assertResponseStatusCodeSame(409);
    }

    public function testPlatformsAreValidated(): void
    {
        $leader = $this->registerClan();

        $this->addElden($leader['token'], []);
        self::assertResponseStatusCodeSame(422);

        $this->addElden($leader['token'], ['dreamcast']);
        self::assertResponseStatusCodeSame(422);
    }

    public function testUnknownGamesAreRejected(): void
    {
        $leader = $this->registerClan();

        $this->api('POST', '/api/me/games', ['source' => 'steam', 'externalId' => '42', 'platforms' => ['pc']], $leader['token']);

        self::assertResponseStatusCodeSame(422);
    }

    public function testCatalogOutageIsReported(): void
    {
        $leader = $this->registerClan();
        HttpClientMock::$catalogDown = true;

        $this->api('GET', '/api/games/search?q=zelda', token: $leader['token']);
        self::assertResponseStatusCodeSame(503);

        $this->addElden($leader['token'], ['pc']);
        self::assertResponseStatusCodeSame(503);
    }

    public function testMemberUpdatesAndRemovesTheirGames(): void
    {
        $leader = $this->registerClan();
        $added = $this->addElden($leader['token'], ['pc']);

        $updated = $this->api('PUT', "/api/me/games/{$added['id']}", ['platforms' => ['xbox_series', 'ps5']], $leader['token']);
        self::assertSame(['ps5', 'xbox_series'], $updated['platforms']);

        $this->api('DELETE', "/api/me/games/{$added['id']}", token: $leader['token']);
        self::assertResponseStatusCodeSame(204);
        self::assertSame([], $this->api('GET', '/api/me/games', token: $leader['token']));
    }

    public function testMembersCannotChangeSomeoneElsesGames(): void
    {
        $leader = $this->registerClan();
        $member = $this->addMember($leader['token'], 'lea');
        $added = $this->addElden($leader['token'], ['pc']);

        $this->api('PUT', "/api/me/games/{$added['id']}", ['platforms' => ['ps5']], $member['token']);
        self::assertResponseStatusCodeSame(404);
        $this->api('DELETE', "/api/me/games/{$added['id']}", token: $member['token']);
        self::assertResponseStatusCodeSame(404);
    }

    public function testClanLibraryListsPlayers(): void
    {
        $martin = $this->registerClan('Martin', 'papa');
        $lea = $this->addMember($martin['token'], 'lea');
        $added = $this->addElden($martin['token'], ['pc']);
        $this->addElden($lea['token'], ['ps5']);

        $library = $this->api('GET', '/api/clan/games', token: $lea['token']);
        self::assertCount(1, $library);
        // Players sorted by name
        self::assertSame(['lea' => ['ps5'], 'papa' => ['pc']], array_column(
            array_map(static fn (array $p): array => [$p['user']['username'], $p['platforms']], $library[0]['players']),
            1,
            0,
        ));
    }

    public function testPlatformsAreListed(): void
    {
        $leader = $this->registerClan();

        $platforms = $this->api('GET', '/api/games/platforms', token: $leader['token']);

        self::assertContains(['value' => 'ps5', 'label' => 'PS5'], $platforms);
    }

    private function addElden(string $token, array $platforms): mixed
    {
        return $this->api('POST', '/api/me/games', ['source' => 'steam', 'externalId' => '1245620', 'platforms' => $platforms], $token);
    }
}
