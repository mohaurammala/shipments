<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Http\CsrfToken;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Support\ArraySession;

final class CsrfTokenTest extends TestCase
{
    #[Test]
    public function theTokenIsLongRandomAndStableWithinASession(): void
    {
        $token = new CsrfToken(new ArraySession());

        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token->value());
        self::assertSame($token->value(), $token->value());
    }

    #[Test]
    public function onlyTheExactTokenMatches(): void
    {
        $token = new CsrfToken(new ArraySession());
        $value = $token->value();

        self::assertTrue($token->matches($value));
        self::assertFalse($token->matches(strtoupper($value)));
        self::assertFalse($token->matches(''));
        self::assertFalse($token->matches(null));
    }

    #[Test]
    public function nothingMatchesBeforeATokenExists(): void
    {
        self::assertFalse((new CsrfToken(new ArraySession()))->matches(''));
    }

    #[Test]
    public function rotatingMakesTheOldTokenWorthless(): void
    {
        $token = new CsrfToken(new ArraySession());
        $old = $token->value();

        $token->rotate();

        self::assertFalse($token->matches($old));
        self::assertNotSame($old, $token->value());
    }
}
