<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Core\Logger;
use App\Core\View;
use App\Http\ErrorHandler;
use App\Http\Kernel;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class KernelTest extends TestCase
{
    /** @var resource */
    private $log;

    private Kernel $kernel;

    protected function setUp(): void
    {
        $log = fopen('php://memory', 'w+b');
        self::assertIsResource($log);
        $this->log = $log;

        $router = new Router();
        $router->get('/ok', static fn (): Response => Response::text('fine'));
        $router->get('/broken', static fn (): Response => throw new LogicException('Bug in a controller'));

        $errors = new ErrorHandler(new Logger($this->log), new View(dirname(__DIR__, 3) . '/views'), false);
        $this->kernel = new Kernel($router, $errors);
    }

    #[Test]
    public function aMatchingRouteIsHandledNormally(): void
    {
        $response = $this->kernel->handle(new Request('GET', '/ok'));

        self::assertSame(200, $response->status());
        self::assertSame('fine', $response->body());
    }

    #[Test]
    public function anUnknownPathGetsTheStyled404Page(): void
    {
        $response = $this->kernel->handle(new Request('GET', '/nowhere'));

        self::assertSame(404, $response->status());
        self::assertStringContainsString('<h1>Page not found</h1>', $response->body());
    }

    #[Test]
    public function aThrowingRouteGetsA500PageWhoseReferenceMatchesTheLog(): void
    {
        $response = $this->kernel->handle(new Request('GET', '/broken'));

        self::assertSame(500, $response->status());
        self::assertStringNotContainsString('Bug in a controller', $response->body());
        self::assertSame(1, preg_match('/reference: <strong>([0-9a-f]{6})<\/strong>/', $response->body(), $matches));

        rewind($this->log);
        $entry = json_decode(trim((string) stream_get_contents($this->log)), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame($matches[1], $entry['context']['ref']);
        self::assertSame('Bug in a controller', $entry['context']['message']);
    }
}
