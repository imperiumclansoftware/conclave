<?php

namespace App\Films;

/** TMDB unreachable, or the API key refused (code 401). */
final class FilmCatalogUnavailableException extends \RuntimeException
{
}
