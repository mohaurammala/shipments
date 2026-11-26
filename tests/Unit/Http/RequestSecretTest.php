<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Http\HttpException;
use App\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RequestSecretTest extends TestCase
{
    #[Test]
    public function aSecretKeepsItsSurroundingSpaces(): void
    {
        $request = new Request('POST', '/login', body: ['password' => '  spaced out  ']);

        self::assertSame('  spaced out  ', $request->secret('password'));
        self::assertSame('spaced out', $request->input('password'));
    }

    #[Test]
    public function aSecretIsStillCheckedLikeAnyOtherInput(): void
    {
        $request = new Request('POST', '/login', body: ['password' => ['an', 'array']]);

        $this->expectException(HttpException::class);

        $request->secret('password');
    }
}
