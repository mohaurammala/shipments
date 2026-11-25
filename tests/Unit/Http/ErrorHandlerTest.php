<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Core\Logger;
use App\Core\View;
use App\Http\ErrorHandler;
use App\Http\HttpException;
use ErrorException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ErrorHandlerTest extends TestCase
{
    /** @var resource */
    private $log;

    private View $views;

    protected function setUp(): void
    {
        $log = fopen('php://memory', 'w+b');
        self::assertIsResource($log);
        $this->log = $log;
        $this->views = new View(dirname(__DIR__, 3) . '/views');
    }

    #[Test]
    public function aClientErrorShowsItsPublicMessageAndIsNotLogged(): void
    {
        $response = $this->handler()->toResponse(HttpException::notFound());

        self::assertSame(404, $response->status());
        self::assertStringContainsString('Page not found', $response->body());
        self::assertStringNotContainsString('reference', $response->body());
        self::assertSame('', $this->logContents());
    }

    #[Test]
    public function aClientErrorKeepsItsHeaders(): void
    {
        $response = $this->handler()->toResponse(HttpException::methodNotAllowed(['GET', 'HEAD']));

        self::assertSame(405, $response->status());
        self::assertSame('GET, HEAD', $response->header('Allow'));
    }

    #[Test]
    public function anUnexpectedErrorShowsOnlyAReferenceThatMatchesTheLog(): void
    {
        $response = $this->handler()->toResponse(new RuntimeException('SQLSTATE[42S02]: table bookings missing'));

        self::assertSame(500, $response->status());
        self::assertSame('no-store', $response->header('Cache-Control'));
        self::assertStringNotContainsString('SQLSTATE', $response->body());
        self::assertSame(1, preg_match('/reference: <strong>([0-9a-f]{6})<\/strong>/', $response->body(), $matches));

        $entry = json_decode($this->logContents(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame($matches[1], $entry['context']['ref']);
        self::assertSame(RuntimeException::class, $entry['context']['exception']);
        self::assertStringContainsString('SQLSTATE', $entry['context']['message']);
    }

    #[Test]
    public function debugModeShowsTheDetailsEscaped(): void
    {
        $response = $this->handler(debug: true)->toResponse(new RuntimeException('<b>broken</b>'));

        self::assertStringContainsString('&lt;b&gt;broken&lt;/b&gt;', $response->body());
        self::assertStringNotContainsString('<b>broken</b>', $response->body());
    }

    #[Test]
    public function ifTheErrorPageItselfFailsPlainTextStillCarriesTheReference(): void
    {
        $handler = new ErrorHandler(new Logger($this->log), new View('/nonexistent'), false);

        $response = $handler->toResponse(new RuntimeException('boom'));

        self::assertSame(500, $response->status());
        self::assertSame('text/plain; charset=utf-8', $response->header('Content-Type'));
        self::assertMatchesRegularExpression('/Reference: [0-9a-f]{6}$/', $response->body());
        self::assertStringContainsString('Error page failed to render', $this->logContents());
    }

    #[Test]
    public function aWarningBecomesAnException(): void
    {
        // Set the level explicitly: PHPUnit narrows error_reporting() while a test runs.
        $previous = error_reporting(E_ALL);

        try {
            $this->handler()->handleError(E_WARNING, 'Undefined array key "x"', __FILE__, __LINE__);
            self::fail('Expected an ErrorException.');
        } catch (ErrorException $e) {
            self::assertSame('Undefined array key "x"', $e->getMessage());
            self::assertSame(E_WARNING, $e->getSeverity());
        } finally {
            error_reporting($previous);
        }
    }

    #[Test]
    public function errorsExcludedByErrorReportingAreLeftAlone(): void
    {
        $previous = error_reporting(E_ALL & ~E_WARNING);

        try {
            self::assertFalse($this->handler()->handleError(E_WARNING, 'ignored'));
        } finally {
            error_reporting($previous);
        }
    }

    private function handler(bool $debug = false): ErrorHandler
    {
        return new ErrorHandler(new Logger($this->log), $this->views, $debug);
    }

    private function logContents(): string
    {
        rewind($this->log);

        return trim((string) stream_get_contents($this->log));
    }
}
