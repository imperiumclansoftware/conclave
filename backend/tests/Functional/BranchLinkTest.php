<?php

namespace App\Tests\Functional;

use App\Tests\Support\HttpClientMock;

final class BranchLinkTest extends ApiTestCase
{
    public function testMembersShareTheFavouriteLinksOfTheirBranches(): void
    {
        $leader = $this->registerClan();
        ['token' => $lea, 'user' => $leaUser] = $this->addMember($leader['token'], 'lea');
        $tom = $this->addMember($leader['token'], 'tom')['token'];
        $branches = array_column($this->api('GET', '/api/admin/branches', token: $leader['token']), null, 'crest');
        foreach (['players', 'cinephiles'] as $crest) {
            $this->api('PUT', "/api/admin/branches/{$branches[$crest]['id']}/active", ['active' => true], $leader['token']);
            $this->api('PUT', "/api/clan/branches/{$branches[$crest]['id']}/membership", token: $lea);
        }
        $this->api('PUT', "/api/clan/branches/{$branches['players']['id']}/membership", token: $tom);
        $players = $branches['players']['id'];

        // A title is optional: the host stands in for it
        $link = $this->api('POST', "/api/clan/branches/$players/links", ['url' => ' https://www.speedrun.com/fr-FR ', 'title' => ''], $lea);
        self::assertResponseStatusCodeSame(201);
        self::assertSame(['speedrun.com', 'https://www.speedrun.com/fr-FR', 'speedrun.com', true], [$link['title'], $link['url'], $link['host'], $link['canRemove']]);
        self::assertCount(2, HttpClientMock::publishedOfType('links'), 'Both members of the branch refresh their sidebar');
        $this->api('POST', "/api/clan/branches/$players/links", ['url' => 'https://discord.gg/imperium', 'title' => 'Salon vocal'], $tom);

        // Grouped by branch, every branch of the member even without links
        $mine = $this->api('GET', '/api/me/branch-links', token: $lea);
        self::assertSame([['Cinéphiles', []], ['Joueurs', ['speedrun.com', 'Salon vocal']]],
            array_map(static fn (array $g): array => [$g['branch']['name'], array_column($g['links'], 'title')], $mine));
        self::assertSame([true, false], array_column($mine[1]['links'], 'canRemove'), 'Only their own links');
        self::assertSame($leaUser['id'], $mine[1]['links'][0]['addedBy']['id']);
        // Not shown outside of the branch
        self::assertSame([], $this->api('GET', '/api/me/branch-links', token: $leader['token']));

        // Only the author or a leader removes a link
        $this->api('DELETE', "/api/branch-links/{$link['id']}", token: $tom);
        self::assertResponseStatusCodeSame(403);
        $this->api('DELETE', "/api/branch-links/{$link['id']}", token: $leader['token']);
        self::assertResponseStatusCodeSame(204);

        // A deactivated branch takes its links away, until it is active again
        $this->api('PUT', "/api/admin/branches/$players/active", ['active' => false], $leader['token']);
        self::assertSame(['Cinéphiles'], array_map(static fn (array $g): string => $g['branch']['name'], $this->api('GET', '/api/me/branch-links', token: $lea)));
    }

    public function testOnlyMembersAddValidLinks(): void
    {
        $leader = $this->registerClan();
        $lea = $this->addMember($leader['token'], 'lea')['token'];
        $players = array_column($this->api('GET', '/api/admin/branches', token: $leader['token']), null, 'crest')['players']['id'];
        $this->api('POST', "/api/clan/branches/$players/links", ['url' => 'https://example.com'], $lea);
        self::assertResponseStatusCodeSame(404, 'Inactive branch');

        $this->api('PUT', "/api/admin/branches/$players/active", ['active' => true], $leader['token']);
        $this->api('POST', "/api/clan/branches/$players/links", ['url' => 'https://example.com'], $lea);
        self::assertResponseStatusCodeSame(403, 'Not a member');

        $this->api('PUT', "/api/clan/branches/$players/membership", token: $lea);
        foreach (['', 'pas une adresse', 'javascript:alert(1)', 'ftp://example.com/fichier', 'https://'.str_repeat('a', 500).'.com'] as $url) {
            $this->api('POST', "/api/clan/branches/$players/links", ['url' => $url], $lea);
            self::assertResponseStatusCodeSame(422, $url);
        }
    }
}
