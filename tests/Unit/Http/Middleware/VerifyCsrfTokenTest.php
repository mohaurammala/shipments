<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Middleware;

use App\Http\CsrfToken;
use App\Http\HttpException;
use App\Http\Middleware\VerifyCsrfToken;
use App\Http\Request;
use App\Http\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Support\ArraySession;

final class VerifyCsrfTokenTest extends TestCase
{
    private CsrfToken $token;

    private VerifyCsrfToken $middleware;

    protected function setUp(): void
    {
        $this->token = new CsrfToken(new ArraySession());
        $this->middleware = new VerifyCsrfToken($this->token);
    }

    #[Test]
    public function readingAPageNeedsNoToken(): void
    {
        self::assertSame('passed', $this->through(new Request('GET', '/login'))->body());
    }

    #[Test]
    public function aPostWithTheSessionTokenPasses(): void
    {
        $request = new Request('POST', '/logout', body: [CsrfToken::FIELD => $this->token->value()]);

        self::assertSame('passed', $this->through($request)->body());
    }

    #[Test]
    public function aPostWithoutTheTokenIsForbidden(): void
    {
        $this->token->value();

        try {
            $this->through(new Request('POST', '/logout'));
            self::fail('Expected a 403.');
        } catch (HttpException $e) {
            self::assertSame(403, $e->status);
        }
    }

    #[Test]
    public function aPostWithAnotherSessionsTokenIsForbidden(): void
    {
        $this->token->value();
        $stolen = (new CsrfToken(new ArraySession()))->value();

        $this->expectException(HttpException::class);

        $this->through(new Request('POST', '/logout', body: [CsrfToken::FIELD => $stolen]));
    }

    private function through(Request $request): Response
    {
        return $this->middleware->process($request, static fn (): Response => Response::text('passed'));
    }
}
