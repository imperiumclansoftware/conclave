<?php

namespace App\Tests\Functional;

final class MemberCardTest extends ApiTestCase
{
    public function testTheCardSumsUpTheMembersBranchesAndActivity(): void
    {
        $leader = $this->registerClan();
        ['token' => $lea, 'user' => $leaUser] = $this->addMember($leader['token'], 'lea');

        $all = $this->api('GET', '/api/admin/branches', token: $leader['token']);
        $byCrest = array_column($all, null, 'crest');
        foreach (['players', 'communicants', 'cinephiles'] as $crest) {
            $this->api('PUT', "/api/admin/branches/{$byCrest[$crest]['id']}/active", ['active' => true], $leader['token']);
        }
        $this->api('PUT', "/api/clan/branches/{$byCrest['players']['id']}/membership", token: $lea);
        $this->api('PUT', "/api/clan/branches/{$byCrest['cinephiles']['id']}/membership", token: $lea);
        $this->api('POST', '/api/me/games', ['source' => 'steam', 'externalId' => '1245620', 'platforms' => ['pc']], $lea);
        self::assertResponseStatusCodeSame(201);

        // Two news of the leader, member of the Communicants
        $this->api('PUT', "/api/clan/branches/{$byCrest['communicants']['id']}/membership", token: $leader['token']);
        foreach (['Une', 'Deux'] as $title) {
            $this->client->request('POST', '/api/news', ['title' => $title, 'body' => 'Texte'], [], ['HTTP_AUTHORIZATION' => 'Bearer '.$leader['token']]);
            self::assertResponseStatusCodeSame(201);
        }

        $card = $this->api('GET', "/api/members/{$leaUser['id']}/card", token: $leader['token']);
        self::assertSame(['lea', 'member'], [$card['username'], $card['role']]);
        self::assertSame([['Cinéphiles', 'cinephiles'], ['Joueurs', 'players']], array_map(static fn (array $b): array => [$b['name'], $b['crest']], $card['branches']));
        self::assertSame(['games' => null, 'news' => 0, 'branches' => 2], $card['stats'], 'Seen by the leader, outside of the Joueurs branch');
        self::assertNotEmpty($card['memberSince']);

        $leaderCard = $this->api('GET', "/api/members/{$leader['user']['id']}/card", token: $lea);
        self::assertSame(['games' => 0, 'news' => 2, 'branches' => 1], $leaderCard['stats']);
        // The leader is not in the Joueurs branch: no games on the cards they see
        self::assertNull($this->api('GET', "/api/members/{$leaUser['id']}/card", token: $leader['token'])['stats']['games']);

        // A deactivated branch disappears from the card
        $this->api('PUT', "/api/admin/branches/{$byCrest['cinephiles']['id']}/active", ['active' => false], $leader['token']);
        self::assertSame(['Joueurs'], array_column($this->api('GET', "/api/members/{$leaUser['id']}/card", token: $leader['token'])['branches'], 'name'));
    }

    public function testOnlyClanMembersHaveACard(): void
    {
        $leader = $this->registerClan();
        $pendingId = $this->signUp('marc');

        $this->api('GET', "/api/members/$pendingId/card", token: $leader['token']);
        self::assertResponseStatusCodeSame(404);
        $this->api('GET', '/api/members/999999/card', token: $leader['token']);
        self::assertResponseStatusCodeSame(404);
    }
}
