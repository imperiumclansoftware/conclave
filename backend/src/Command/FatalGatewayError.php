<?php

namespace App\Command;

/**
 * The gateway refused the connection for a reason reconnecting won't fix.
 */
final class FatalGatewayError extends \RuntimeException
{
}
