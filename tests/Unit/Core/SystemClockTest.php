<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\SystemClock;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SystemClockTest extends TestCase
{
    #[Test]
    public function itReturnsTheCurrentTimeInUtc(): void
    {
        $before = time();
        $now = (new SystemClock())->now();
        $after = time();

        self::assertSame('UTC', $now->getTimezone()->getName());
        self::assertGreaterThanOrEqual($before, $now->getTimestamp());
        self::assertLessThanOrEqual($after, $now->getTimestamp());
    }
}
