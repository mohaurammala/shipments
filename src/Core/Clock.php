<?php

declare(strict_types=1);

namespace App\Core;

use DateTimeImmutable;

/**
 * The current time, injected rather than read directly, so tests can fix it (for example
 * to make a quote expire, or a booking look stuck in CREATING).
 */
interface Clock
{
    /**
     * Always in UTC, matching what the database stores.
     */
    public function now(): DateTimeImmutable;
}
