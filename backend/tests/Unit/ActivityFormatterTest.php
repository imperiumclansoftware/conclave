<?php

namespace App\Tests\Unit;

use App\Discord\Gateway\ActivityFormatter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ActivityFormatterTest extends TestCase
{
    #[DataProvider('activities')]
    public function testFormat(array $activities, ?string $expected): void
    {
        self::assertSame($expected, ActivityFormatter::format($activities));
    }

    public static function activities(): iterable
    {
        yield 'nothing' => [[], null];
        yield 'playing' => [[['type' => 0, 'name' => 'Elden Ring']], 'Joue à Elden Ring'];
        yield 'game wins over custom status' => [[['type' => 4, 'state' => 'Occupé'], ['type' => 0, 'name' => 'Fortnite']], 'Joue à Fortnite'];
        yield 'custom status only' => [[['type' => 4, 'name' => 'Custom Status', 'state' => 'En pause']], 'En pause'];
        yield 'listening' => [[['type' => 2, 'name' => 'Spotify', 'details' => 'Ezio\'s Family']], 'Écoute Ezio\'s Family'];
        yield 'streaming' => [[['type' => 1, 'name' => 'Twitch']], 'En stream : Twitch'];
    }
}
