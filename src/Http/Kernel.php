<?php

declare(strict_types=1);

namespace App\Http;

use App\Http\Middleware\Middleware;
use Throwable;

/**
 * One request in, one response out. Whatever a route throws becomes a safe error page
 * here, so the front controller never has to handle failures itself.
 *
 * Global middleware runs outside that error handling, so it also sees error pages: that is
 * how security headers reach a 404 or a 500.
 */
final readonly class Kernel
{
    /**
     * @param list<Middleware> $middleware Applied to every response, outermost first.
     */
    public function __construct(
        private Router $router,
        private ErrorHandler $errorHandler,
        private array $middleware = [],
    ) {
    }

    public function handle(Request $request): Response
    {
        $next = function (Request $request): Response {
            try {
                return $this->router->dispatch($request);
            } catch (Throwable $e) {
                return $this->errorHandler->toResponse($e);
            }
        };

        foreach (array_reverse($this->middleware) as $layer) {
            $next = static fn (Request $request): Response => $layer->process($request, $next);
        }

        return $next($request);
    }
}
