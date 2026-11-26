<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Core\Logger;
use App\Core\View;
use App\Http\ErrorHandler;
use App\Http\Kernel;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Request;
use App\Http\Router;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class KernelMiddlewareTest extends TestCase
{
    #[Test]
    public function globalMiddlewareAlsoReachesErrorPages(): void
    {
        $log = fopen('php://memory', 'w+b');
        self::assertIsResource($log);

        $kernel = new Kernel(
            new Router(),
            new ErrorHandler(new Logger($log), new View(dirname(__DIR__, 3) . '/views'), false),
            [new SecurityHeaders(https: false)],
        );

        $response = $kernel->handle(new Request('GET', '/nowhere'));

        self::assertSame(404, $response->status());
        self::assertSame('nosniff', $response->header('X-Content-Type-Options'));
    }
}
