<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Http\Response;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TrustedRedirectTest extends TestCase
{
    #[Test]
    public function aLabelLinkOnShiplogicsHostIsFollowed(): void
    {
        $url = 'https://labels.prod-shared.shiplogic.com/sandbox_Stickers_1.pdf?Expires=1&Signature=a~b';

        $response = Response::redirectToTrustedHost($url, ['.shiplogic.com']);

        self::assertSame(303, $response->status());
        self::assertSame($url, $response->header('Location'));
        self::assertSame('no-store', $response->header('Cache-Control'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function untrusted(): iterable
    {
        yield 'plain http' => ['http://labels.prod-shared.shiplogic.com/a.pdf'];
        yield 'another site' => ['https://evil.example/a.pdf'];
        yield 'look-alike domain' => ['https://evilshiplogic.com/a.pdf'];
        yield 'shiplogic as a subdomain of another site' => ['https://shiplogic.com.evil.example/a.pdf'];
        yield 'header injection' => ["https://labels.shiplogic.com/a.pdf\r\nSet-Cookie: x=y"];
        yield 'javascript' => ['javascript:alert(1)'];
    }

    #[Test]
    #[DataProvider('untrusted')]
    public function anythingElseIsRefused(string $url): void
    {
        $this->expectException(InvalidArgumentException::class);

        Response::redirectToTrustedHost($url, ['.shiplogic.com']);
    }
}
