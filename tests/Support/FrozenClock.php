<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Core\Interfaces\Clock;
use DateTimeImmutable;
use DateTimeZone;

/**
 * A clock that stands still until a test moves it, e.g. advance('+31 minutes').
 */
final class FrozenClock implements Clock
{
    private DateTimeImmutable $now;

    public function __construct(string $at = '2026-11-25 10:00:00')
    {
        $this->now = new DateTimeImmutable($at, new DateTimeZone('UTC'));
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    public function advance(string $modifier): void
    {
        $this->now = $this->now->modify($modifier);
    }
}
