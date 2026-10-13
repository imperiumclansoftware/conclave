<?php

namespace App\Discord\Gateway;

/**
 * Turns Discord activities into a short French label ("Joue à Elden Ring").
 */
final class ActivityFormatter
{
    private const PLAYING = 0;
    private const STREAMING = 1;
    private const LISTENING = 2;
    private const WATCHING = 3;
    private const CUSTOM = 4;
    private const COMPETING = 5;

    /**
     * @param list<array{type?: int, name?: string, details?: ?string, state?: ?string}> $activities
     */
    public static function format(array $activities): ?string
    {
        $custom = null;
        foreach ($activities as $activity) {
            $name = trim((string) ($activity['name'] ?? ''));
            $label = match ($activity['type'] ?? null) {
                self::PLAYING => '' !== $name ? "Joue à $name" : null,
                self::STREAMING => '' !== $name ? "En stream : $name" : null,
                self::LISTENING => 'Écoute '.(trim((string) ($activity['details'] ?? '')) ?: $name),
                self::WATCHING => '' !== $name ? "Regarde $name" : null,
                self::COMPETING => '' !== $name ? "En compétition : $name" : null,
                default => null,
            };
            if (null !== $label) {
                return $label;
            }
            if (self::CUSTOM === ($activity['type'] ?? null) && null === $custom) {
                $custom = trim((string) ($activity['state'] ?? '')) ?: null;
            }
        }

        return $custom;
    }
}
