<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Http\HttpException;
use App\Http\Middleware\Middleware;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    #[Test]
    public function itDispatchesToTheMatchingRoute(): void
    {
        $router = new Router();
        $router->get('/health', static fn (): Response => Response::text('ok'));

        self::assertSame('ok', $router->dispatch(new Request('GET', '/health'))->body());
    }

    #[Test]
    public function itPassesNamedParametersToTheHandler(): void
    {
        $router = new Router();
        $router->post(
            '/bookings/{id}/quote',
            static fn (Request $request): Response => Response::text((string) $request->routeParam('id')),
        );

        $response = $router->dispatch(new Request('POST', '/bookings/7f3a9c/quote'));

        self::assertSame('7f3a9c', $response->body());
    }

    #[Test]
    public function aParameterNeverSpansMoreThanOneSegment(): void
    {
        $router = new Router();
        $router->get('/bookings/{id}', static fn (): Response => Response::text('matched'));

        $this->expectExceptionObject(HttpException::notFound());

        $router->dispatch(new Request('GET', '/bookings/abc/extra'));
    }

    #[Test]
    public function patternTextIsMatchedLiterally(): void
    {
        $router = new Router();
        $router->get('/a.b', static fn (): Response => Response::text('dot'));

        $this->expectExceptionObject(HttpException::notFound());

        $router->dispatch(new Request('GET', '/aXb'));
    }

    #[Test]
    public function anUnknownPathIsA404(): void
    {
        $router = new Router();

        try {
            $router->dispatch(new Request('GET', '/nowhere'));
            self::fail('Expected a 404.');
        } catch (HttpException $e) {
            self::assertSame(404, $e->status);
        }
    }

    #[Test]
    public function theWrongMethodIsA405ListingTheAllowedOnes(): void
    {
        $router = new Router();
        $router->get('/login', static fn (): Response => Response::text('form'));
        $router->post('/login', static fn (): Response => Response::text('submitted'));
        $router->get('/health', static fn (): Response => Response::text('ok'));

        try {
            $router->dispatch(new Request('POST', '/health'));
            self::fail('Expected a 405.');
        } catch (HttpException $e) {
            self::assertSame(405, $e->status);
            self::assertSame('GET, HEAD', $e->headers['Allow']);
        }
    }

    #[Test]
    public function headIsServedByTheGetRoute(): void
    {
        $router = new Router();
        $router->get('/health', static fn (): Response => Response::text('ok'));

        self::assertSame(200, $router->dispatch(new Request('HEAD', '/health'))->status());
    }

    #[Test]
    public function middlewareRunsOutermostFirstAndCanStopTheRequest(): void
    {
        $calls = [];
        $recorder = static function (string $name) use (&$calls): Middleware {
            return new class ($name, $calls) implements Middleware {
                /** @param list<string> $calls */
                public function __construct(private string $name, private array &$calls)
                {
                }

                public function process(Request $request, callable $next): Response
                {
                    $this->calls[] = $this->name;

                    return $next($request);
                }
            };
        };
        $gate = new class implements Middleware {
            public function process(Request $request, callable $next): Response
            {
                return Response::redirect('/login');
            }
        };

        $router = new Router();
        $router->get('/open', static fn (): Response => Response::text('handler'), [$recorder('outer'), $recorder('inner')]);
        $router->get('/closed', static fn (): Response => Response::text('handler'), [$gate]);

        self::assertSame('handler', $router->dispatch(new Request('GET', '/open'))->body());
        self::assertSame(['outer', 'inner'], $calls);
        self::assertSame('/login', $router->dispatch(new Request('GET', '/closed'))->header('Location'));
    }
}
