<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Uuid;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UuidTest extends TestCase
{
    #[Test]
    public function itProducesAVersion4Uuid(): void
    {
        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            Uuid::v4(),
        );
    }

    #[Test]
    public function itDoesNotRepeat(): void
    {
        $ids = array_map(static fn (): string => Uuid::v4(), range(1, 1000));

        self::assertCount(1000, array_unique($ids));
    }
}
