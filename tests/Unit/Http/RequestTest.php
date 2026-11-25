<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Http\HttpException;
use App\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RequestTest extends TestCase
{
    #[Test]
    public function itTrimsInputAndReturnsNullForMissingFields(): void
    {
        $request = new Request('POST', '/bookings', body: ['company' => "  Acme  \n"]);

        self::assertSame('Acme', $request->input('company'));
        self::assertNull($request->input('missing'));
    }

    #[Test]
    public function itNeverEscapesInputBecauseEscapingHappensOnOutput(): void
    {
        $request = new Request('POST', '/bookings', body: ['company' => "Smith & Sons <O'Brien>"]);

        self::assertSame("Smith & Sons <O'Brien>", $request->input('company'));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function invalidSingleValues(): iterable
    {
        yield 'array where a single value is expected' => [['a', 'b']];
        yield 'invalid UTF-8' => ["caf\xE9"];
        yield 'NUL byte' => ["abc\0def"];
        yield 'oversized' => [str_repeat('x', Request::MAX_FIELD_BYTES + 1)];
    }

    #[Test]
    #[DataProvider('invalidSingleValues')]
    public function itRejectsInvalidSingleValuesWithA400(mixed $value): void
    {
        $request = new Request('POST', '/bookings', body: ['field' => $value]);

        try {
            $request->input('field');
            self::fail('Expected a 400.');
        } catch (HttpException $e) {
            self::assertSame(400, $e->status);
        }
    }

    #[Test]
    public function queryValuesAreNormalisedTheSameWay(): void
    {
        $request = new Request('GET', '/bookings', query: ['page' => ' 2 ']);

        self::assertSame('2', $request->query('page'));
    }

    #[Test]
    public function itNormalisesEveryLeafOfANestedArray(): void
    {
        $request = new Request('POST', '/bookings', body: [
            'parcels' => [
                ['description' => ' Books ', 'weight_kg' => ' 2.5 '],
                ['description' => 'Shoes', 'weight_kg' => '1'],
            ],
        ]);

        self::assertSame([
            ['description' => 'Books', 'weight_kg' => '2.5'],
            ['description' => 'Shoes', 'weight_kg' => '1'],
        ], $request->inputArray('parcels'));
        self::assertSame([], $request->inputArray('missing'));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function invalidArrays(): iterable
    {
        yield 'single value where an array is expected' => ['just text'];
        yield 'too deep' => [[[['too' => ['deep' => 'x']]]]];
        yield 'too many items' => [array_fill(0, Request::MAX_ARRAY_ITEMS + 1, 'x')];
        yield 'invalid UTF-8 in a leaf' => [[['description' => "caf\xE9"]]];
    }

    #[Test]
    #[DataProvider('invalidArrays')]
    public function itRejectsInvalidArraysWithA400(mixed $value): void
    {
        $request = new Request('POST', '/bookings', body: ['parcels' => $value]);

        $this->expectException(HttpException::class);

        $request->inputArray('parcels');
    }

    #[Test]
    public function routeParamsAreAddedWithoutChangingTheOriginal(): void
    {
        $original = new Request('GET', '/bookings/abc');
        $withParams = $original->withRouteParams(['id' => 'abc']);

        self::assertSame('abc', $withParams->routeParam('id'));
        self::assertNull($original->routeParam('id'));
    }

    #[Test]
    public function itIsBuiltFromTheSuperglobals(): void
    {
        $saved = [$_SERVER, $_GET, $_POST];
        $_SERVER['REQUEST_METHOD'] = 'post';
        $_SERVER['REQUEST_URI'] = '/bookings/42?tab=rates';
        $_SERVER['REMOTE_ADDR'] = '172.19.0.1';
        $_GET = ['tab' => 'rates'];
        $_POST = ['company' => ' Acme '];

        try {
            $request = Request::fromGlobals();

            self::assertSame('POST', $request->method());
            self::assertSame('/bookings/42', $request->path());
            self::assertSame('rates', $request->query('tab'));
            self::assertSame('Acme', $request->input('company'));
            self::assertSame('172.19.0.1', $request->ip());
        } finally {
            [$_SERVER, $_GET, $_POST] = $saved;
        }
    }
}
