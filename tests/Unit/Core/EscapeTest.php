<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EscapeTest extends TestCase
{
    #[Test]
    public function itEscapesEveryCharacterThatCanBreakOutOfHtmlOrAnAttribute(): void
    {
        self::assertSame('&lt;a href=&quot;x&quot; title=&apos;y&apos;&gt;Smith &amp; Sons&lt;/a&gt;', e('<a href="x" title=\'y\'>Smith & Sons</a>'));
    }

    #[Test]
    public function itAcceptsNumbersAndNull(): void
    {
        self::assertSame('42', e(42));
        self::assertSame('99.5', e(99.5));
        self::assertSame('', e(null));
    }

    #[Test]
    public function invalidUtf8IsReplacedRatherThanReturnedAsAnEmptyString(): void
    {
        self::assertSame("caf\u{FFFD}", e("caf\xE9"));
    }
}
