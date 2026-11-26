<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\SecurityHeaders;
use App\Http\Request;
use App\Http\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SecurityHeadersTest extends TestCase
{
    #[Test]
    public function everyResponseGetsTheBrowserProtections(): void
    {
        $response = $this->through(new SecurityHeaders(https: false));

        self::assertStringContainsString("default-src 'self'", (string) $response->header('Content-Security-Policy'));
        self::assertStringContainsString("frame-ancestors 'none'", (string) $response->header('Content-Security-Policy'));
        self::assertSame('nosniff', $response->header('X-Content-Type-Options'));
        self::assertSame('same-origin', $response->header('Referrer-Policy'));
        self::assertSame('DENY', $response->header('X-Frame-Options'));
    }

    #[Test]
    public function strictTransportSecurityIsSentOnlyOverHttps(): void
    {
        self::assertNull($this->through(new SecurityHeaders(https: false))->header('Strict-Transport-Security'));
        self::assertSame('max-age=31536000', $this->through(new SecurityHeaders(https: true))->header('Strict-Transport-Security'));
    }

    private function through(SecurityHeaders $middleware): Response
    {
        return $middleware->process(new Request('GET', '/'), static fn (): Response => Response::text('page'));
    }
}
