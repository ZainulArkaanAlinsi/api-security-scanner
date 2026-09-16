<?php

namespace App\Services;

use RuntimeException;

/**
 * Thrown when a target cannot be scanned (invalid URL, blocked address,
 * unreachable host). The message is safe to show to the user.
 */
class ScanException extends RuntimeException
{
}
