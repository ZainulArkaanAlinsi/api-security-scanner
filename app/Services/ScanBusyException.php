<?php

namespace App\Services;

use RuntimeException;

/**
 * Thrown when a scan for the same ticket is already running.
 */
class ScanBusyException extends RuntimeException {}
