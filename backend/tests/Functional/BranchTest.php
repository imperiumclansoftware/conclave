<?php

namespace App\Tests\Functional;

use App\Tests\Support\HttpClientMock;

final class BranchTest extends ApiTestCase
{
    public function testLeadersActivateTheBranchesOfTheCatalogue(): void
    {
        $leader = $this->registerClan();
        ['token' => $marcToken, 'user' => $marc] = $this->addMember($leader['token'], 'marc');

        // The catalogue: one branch per coat of arms, inactive until a leader opens it
        $all = $this->api('GET', '/api/admin/branches', token: $leader['token']);
        self::assertSame([['Cinéphiles', 'cinephiles', false], ['Communicants', 'communicants', false], ['Développeurs', 'developers', false], ['Joueurs', 'players', false]],
            array_map(static fn (array $b): array => [$b['name'], $b['crest'], $b['active']], $all));
        self::assertCount(4, $this->api('GET', '/api/admin/branches', token: $leader['token']), 'Never added twice');
        self::assertSame([], $this->api('GET', '/api/clan/branches', token: $marcToken));
        $players = $this->branch($all, 'players');
        $this->api('PUT', "/api/clan/branches/{$players['id']}/membership", token: $marcToken);
        self::assertResponseStatusCodeSame(404);

        $activated = $this->api('PUT', "/api/admin/branches/{$players['id']}/active", ['active' => true], $leader['token']);
        self::assertTrue($activated['active']);
        $this->api('PUT', "/api/admin/branches/{$this->branch($all, 'developers')['id']}/active", ['active' => true], $leader['token']);
        self::assertSame(['Développeurs', 'Joueurs'], array_column($this->api('GET', '/api/clan/branches', token: $marcToken), 'name'));

        // Members join one or several branches, and can leave them
        $joined = $this->api('PUT', "/api/clan/branches/{$players['id']}/membership", token: $marcToken);
        self::assertSame([$marc['id']], $joined['memberIds']);
        $this->api('PUT', "/api/clan/branches/{$this->branch($all, 'developers')['id']}/membership", token: $marcToken);
        $profile = $this->api('GET', "/api/members/{$marc['id']}", token: $leader['token']);
        self::assertSame([['Développeurs', 'developers'], ['Joueurs', 'players']], array_map(static fn (array $b): array => [$b['name'], $b['crest']], $profile['branches']));
        $left = $this->api('DELETE', "/api/clan/branches/{$players['id']}/membership", token: $marcToken);
        self::assertSame([], $left['memberIds']);
    }

    public function testDeactivatingHidesTheBranchAndKeepsItsMembers(): void
    {
        $leader = $this->registerClan();
        ['token' => $marcToken, 'user' => $marc] = $this->addMember($leader['token'], 'marc');
        $players = $this->branch($this->api('GET', '/api/admin/branches', token: $leader['token']), 'players');
        $this->api('PUT', "/api/admin/branches/{$players['id']}/active", ['active' => true], $leader['token']);
        $this->api('PUT', "/api/clan/branches/{$players['id']}/membership", token: $marcToken);

        $this->api('PUT', "/api/admin/branches/{$players['id']}/active", ['active' => false], $leader['token']);
        self::assertSame([], $this->api('GET', '/api/clan/branches', token: $marcToken));
        self::assertSame([], $this->api('GET', '/api/me/profile', token: $marcToken)['branches']);
        self::assertCount(1, HttpClientMock::publishedOfType('branches'), 'Its members are told');

        $reactivated = $this->api('PUT', "/api/admin/branches/{$players['id']}/active", ['active' => true], $leader['token']);
        self::assertSame([$marc['id']], $reactivated['memberIds']);
        self::assertSame(['Joueurs'], array_column($this->api('GET', '/api/me/profile', token: $marcToken)['branches'], 'name'));
    }

    public function testLeadersRenameBranchesWithUniqueNames(): void
    {
        $leader = $this->registerClan();
        $all = $this->api('GET', '/api/admin/branches', token: $leader['token']);
        $players = $this->branch($all, 'players');

        $renamed = $this->api('PUT', "/api/admin/branches/{$players['id']}", ['name' => '  Section   PC ', 'description' => 'Les joueurs PC', 'crest' => 'developers'], $leader['token']);
        self::assertSame(['Section PC', 'Les joueurs PC', 'players', false, false], [$renamed['name'], $renamed['description'], $renamed['crest'], $renamed['publishesNews'], $renamed['approvalRequired']], 'The coat of arms comes with the branch');
        $this->api('PUT', "/api/admin/branches/{$players['id']}", ['name' => 'Section PC', 'description' => ''], $leader['token']);
        self::assertResponseIsSuccessful();

        $result = $this->api('PUT', "/api/admin/branches/{$this->branch($all, 'developers')['id']}", ['name' => 'section pc'], $leader['token']);
        self::assertResponseStatusCodeSame(409);
        self::assertSame('La branche « section pc » existe déjà.', $result['detail']);
        $this->api('PUT', "/api/admin/branches/{$players['id']}", ['name' => '   '], $leader['token']);
        self::assertResponseStatusCodeSame(422);
    }

    public function testBranchesAreNeitherCreatedNorDeletedAndOnlyLeadersManageThem(): void
    {
        $leader = $this->registerClan();
        $marcToken = $this->addMember($leader['token'], 'marc')['token'];
        $players = $this->branch($this->api('GET', '/api/admin/branches', token: $leader['token']), 'players');

        $this->api('POST', '/api/admin/branches', ['name' => 'Nouvelle'], $leader['token']);
        self::assertResponseStatusCodeSame(405);
        $this->api('DELETE', "/api/admin/branches/{$players['id']}", token: $leader['token']);
        self::assertResponseStatusCodeSame(405);

        $this->api('GET', '/api/admin/branches', token: $marcToken);
        self::assertResponseStatusCodeSame(403);
        $this->api('PUT', "/api/admin/branches/{$players['id']}/active", ['active' => true], $marcToken);
        self::assertResponseStatusCodeSame(403);
        $this->api('PUT', "/api/admin/branches/{$players['id']}", ['name' => 'Autre'], $marcToken);
        self::assertResponseStatusCodeSame(403);
        $this->api('PUT', '/api/clan/branches/999999/membership', token: $marcToken);
        self::assertResponseStatusCodeSame(404);
    }

    private function branch(array $branches, string $crest): array
    {
        foreach ($branches as $branch) {
            if ($crest === $branch['crest']) {
                return $branch;
            }
        }
        self::fail("No $crest branch.");
    }
}
