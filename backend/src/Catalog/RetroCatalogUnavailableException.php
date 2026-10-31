<?php

namespace App\Catalog;

/** No retro search: RAWG, the only catalog with older consoles, is not usable. The message says why. */
final class RetroCatalogUnavailableException extends \RuntimeException
{
}
