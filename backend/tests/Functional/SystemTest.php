<?php

namespace App\Tests\Functional;

final class SystemTest extends ApiTestCase
{
    public function testLeadersSeeTheStateOfTheInstallation(): void
    {
        $leader = $this->registerClan();
        ['token' => $marc] = $this->addMember($leader['token'], 'marc');
        $this->api('GET', '/api/admin/system', token: $marc);
        self::assertResponseStatusCodeSame(403);

        $conversation = $this->api('POST', '/api/conversations', ['userId' => $this->api('GET', '/api/me', token: $marc)['id']], $leader['token']);
        $this->api('POST', "/api/conversations/{$conversation['id']}/messages", ['content' => 'Salut'], $leader['token']);

        $report = $this->api('GET', '/api/admin/system', token: $leader['token']);
        self::assertResponseIsSuccessful();
        self::assertSame(['dev', 'test'], [$report['application']['version'], $report['application']['environment']]);

        $services = array_column($report['services'], 'state', 'id');
        self::assertSame('ok', $services['database']);
        self::assertSame('ok', $services['mercure']);
        self::assertSame('ok', $services['jwt']);
        self::assertSame('off', $services['tmdb'], 'No key in the tests');
        self::assertArrayHasKey('discord', $services);

        // The test database is created from the mapping: its migrations are not recorded
        $migrations = $report['database']['migrations'];
        self::assertSame(\count(glob(__DIR__.'/../../migrations/Version*.php')), $migrations['executed'] + $migrations['pending']);
        self::assertGreaterThan(0, $report['database']['sizeBytes']);
        self::assertSame([2, 0, 1], [$report['activity']['members'], $report['activity']['pendingSignups'], $report['activity']['messages24h']]);

        $days = $report['activity']['messagesByDay'];
        self::assertCount(14, $days);
        self::assertSame(1, $days[13]['count'], 'Today');
        self::assertSame((new \DateTimeImmutable('today', new \DateTimeZone('Europe/Paris')))->format('Y-m-d'), $days[13]['day']);
    }
}
