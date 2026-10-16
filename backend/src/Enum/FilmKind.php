<?php

namespace App\Enum;

/** What a TMDB fiche describes; the values are TMDB's own media types. */
enum FilmKind: string
{
    case Movie = 'movie';
    case Tv = 'tv';

    public function label(): string
    {
        return match ($this) {
            self::Movie => 'Film',
            self::Tv => 'Série',
        };
    }
}
