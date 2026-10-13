<?php

namespace App\Tests\Functional;

use App\Tests\Support\HttpClientMock;
use Symfony\Component\Mime\Email;

final class ClanTest extends ApiTestCase
{
    public function testSignUpWaitsForALeaderAndAlertsThem(): void
    {
        $leader = $this->registerClan();
        $pendingId = $this->signUp('marc');

        // Leaders are alerted by e-mail (messages are logged per request: check right away) and in the app
        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertEmailAddressContains($email, 'To', 'papa@example.com');
        self::assertEmailHtmlBodyContains($email, 'Marc');
        self::assertEmailHtmlBodyContains($email, 'https://app.test/admin');
        self::assertCount(1, HttpClientMock::publishedOfType('signups'));

        // Cannot log in yet
        $result = $this->api('POST', '/api/login', ['username' => 'marc', 'password' => 'motdepasse']);
        self::assertResponseStatusCodeSame(401);
        self::assertSame('Votre compte attend la validation d\'un dirigeant du clan.', $result['message']);

        // Not a member yet
        self::assertSame(['papa'], array_column($this->api('GET', '/api/clan/members', token: $leader['token']), 'username'));
        $signups = $this->api('GET', '/api/admin/signups', token: $leader['token']);
        self::assertSame([[$pendingId, 'marc@example.com', 'pending']], array_map(static fn (array $s): array => [$s['id'], $s['email'], $s['status']], $signups));
    }

    public function testLeaderApprovesASignUp(): void
    {
        $leader = $this->registerClan();
        $pendingId = $this->signUp('marc');

        $this->api('POST', "/api/admin/signups/$pendingId/approve", token: $leader['token']);
        self::assertResponseIsSuccessful();
        $welcome = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $welcome);
        self::assertEmailAddressContains($welcome, 'To', 'marc@example.com');
        self::assertStringContainsString('validée', $welcome->getHtmlBody());

        self::assertNotEmpty($this->login('marc'));
        self::assertEqualsCanonicalizing(['papa', 'marc'], array_column($this->api('GET', '/api/clan/members', token: $leader['token']), 'username'));
        self::assertSame([], $this->api('GET', '/api/admin/signups', token: $leader['token']));
        self::assertCount(1, HttpClientMock::publishedOfType('member-joined'));
    }

    public function testLeaderRejectsASignUp(): void
    {
        $leader = $this->registerClan();
        $pendingId = $this->signUp('marc');

        $this->api('POST', "/api/admin/signups/$pendingId/reject", token: $leader['token']);
        self::assertResponseStatusCodeSame(204);
        self::assertEmailAddressContains(self::getMailerMessage(), 'To', 'marc@example.com');
        $this->api('POST', '/api/login', ['username' => 'marc', 'password' => 'motdepasse']);
        self::assertResponseStatusCodeSame(401);
        // The username is free again
        $this->signUp('marc');
    }

    public function testSignUpValidation(): void
    {
        $this->registerClan();

        $this->api('POST', '/api/register', ['username' => 'papa', 'displayName' => 'Autre', 'email' => 'autre@example.com', 'password' => 'motdepasse']);
        self::assertResponseStatusCodeSame(422);
        $this->api('POST', '/api/register', ['username' => 'autre', 'displayName' => 'Autre', 'email' => 'papa@example.com', 'password' => 'motdepasse']);
        self::assertResponseStatusCodeSame(422);
        $this->api('POST', '/api/register', ['username' => 'autre', 'displayName' => 'Autre', 'email' => 'pas-un-email', 'password' => 'motdepasse']);
        self::assertResponseStatusCodeSame(422);
    }

    public function testOnlyLeadersAdministerTheClan(): void
    {
        $leader = $this->registerClan();
        $member = $this->addMember($leader['token'], 'lea');

        foreach ([['GET', '/api/admin/settings'], ['GET', '/api/admin/signups'], ['GET', '/api/admin/members']] as [$method, $uri]) {
            $this->api($method, $uri, token: $member['token']);
            self::assertResponseStatusCodeSame(403);
        }
        $this->api('POST', '/api/admin/members', ['username' => 'x', 'displayName' => 'X', 'password' => 'motdepasse', 'role' => 'leader'], $member['token']);
        self::assertResponseStatusCodeSame(403);
    }

    public function testLeaderManagesRoles(): void
    {
        $leader = $this->registerClan();
        $member = $this->addMember($leader['token'], 'lea');

        // The only leader cannot step down
        $this->api('PUT', "/api/admin/members/{$leader['user']['id']}/role", ['role' => 'member'], $leader['token']);
        self::assertResponseStatusCodeSame(409);

        $promoted = $this->api('PUT', "/api/admin/members/{$member['user']['id']}/role", ['role' => 'leader'], $leader['token']);
        self::assertSame('leader', $promoted['role']);
        // Roles are read from the database on each request: Léa can administer right away
        $this->api('GET', '/api/admin/members', token: $member['token']);
        self::assertResponseIsSuccessful();

        $this->api('PUT', "/api/admin/members/{$leader['user']['id']}/role", ['role' => 'member'], $leader['token']);
        self::assertResponseIsSuccessful();
    }

    public function testLeaderRemovesAMember(): void
    {
        $leader = $this->registerClan();
        $member = $this->addMember($leader['token'], 'lea');

        $this->api('DELETE', "/api/admin/members/{$leader['user']['id']}", token: $leader['token']);
        self::assertResponseStatusCodeSame(409);

        $this->api('DELETE', "/api/admin/members/{$member['user']['id']}", token: $leader['token']);
        self::assertResponseStatusCodeSame(204);
        self::assertCount(1, HttpClientMock::publishedOfType('member-left'));
        self::assertSame(['papa'], array_column($this->api('GET', '/api/admin/members', token: $leader['token']), 'username'));
        $this->api('GET', '/api/me', token: $member['token']);
        self::assertResponseStatusCodeSame(401);
    }

    public function testPasswordReset(): void
    {
        $leader = $this->registerClan();

        // Same answer whether the account exists or not
        $this->api('POST', '/api/password/forgot', ['identifier' => 'inconnu']);
        self::assertResponseStatusCodeSame(204);
        self::assertEmailCount(0);

        $this->api('POST', '/api/password/forgot', ['identifier' => 'PAPA@example.com']);
        self::assertResponseStatusCodeSame(204);
        self::assertEmailCount(1);
        preg_match('#https://app\.test/reset-password\?token=([^"&]+)#', self::getMailerMessage()->getHtmlBody(), $match);
        $token = urldecode(html_entity_decode($match[1]));

        $this->api('POST', '/api/password/reset', ['token' => $token, 'password' => 'nouveau-secret']);
        self::assertResponseStatusCodeSame(204);
        self::assertNotEmpty($this->login('papa', 'nouveau-secret'));

        // Single use: the password changed, so the token is no longer valid
        $result = $this->api('POST', '/api/password/reset', ['token' => $token, 'password' => 'encore-autre']);
        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('invalide ou a expiré', $result['detail']);
        $this->api('POST', '/api/password/reset', ['token' => 'forged.token', 'password' => 'encore-autre']);
        self::assertResponseStatusCodeSame(422);
    }

    public function testApiRequiresAuthentication(): void
    {
        $this->api('GET', '/api/conversations');

        self::assertResponseStatusCodeSame(401);
    }
}
