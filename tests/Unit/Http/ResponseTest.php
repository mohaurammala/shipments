<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Http\Response;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ResponseTest extends TestCase
{
    #[Test]
    public function htmlAndTextSetTheirContentTypes(): void
    {
        self::assertSame('text/html; charset=utf-8', Response::html('<p>hi</p>')->header('Content-Type'));
        self::assertSame('text/plain; charset=utf-8', Response::text('hi', 503)->header('content-type'));
        self::assertSame(503, Response::text('hi', 503)->status());
    }

    #[Test]
    public function aRedirectIsA303ToALocalPathByDefault(): void
    {
        $response = Response::redirect('/bookings/abc');

        self::assertSame(303, $response->status());
        self::assertSame('/bookings/abc', $response->header('Location'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function offSiteTargets(): iterable
    {
        yield 'absolute URL' => ['https://evil.example'];
        yield 'protocol-relative' => ['//evil.example/login'];
        yield 'backslash trick' => ['/\\evil.example'];
        yield 'header injection' => ["/ok\r\nSet-Cookie: session=stolen"];
        yield 'relative path' => ['bookings'];
    }

    #[Test]
    #[DataProvider('offSiteTargets')]
    public function itRefusesToRedirectOffSite(string $target): void
    {
        $this->expectException(InvalidArgumentException::class);

        Response::redirect($target);
    }

    #[Test]
    public function withHeaderReturnsANewResponse(): void
    {
        $original = Response::text('ok');
        $changed = $original->withHeader('Cache-Control', 'no-store');

        self::assertSame('no-store', $changed->header('Cache-Control'));
        self::assertNull($original->header('Cache-Control'));
    }

    #[Test]
    public function withHeaderRejectsLineBreaks(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Response::text('ok')->withHeader('X-Test', "a\r\nSet-Cookie: x=y");
    }
}
