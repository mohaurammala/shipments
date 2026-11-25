<?php

declare(strict_types=1);

namespace App\Http;

use Throwable;

/**
 * One request in, one response out. Whatever a route throws becomes a safe error page
 * here, so the front controller never has to handle failures itself.
 */
final readonly class Kernel
{
    public function __construct(
        private Router $router,
        private ErrorHandler $errorHandler,
    ) {
    }

    public function handle(Request $request): Response
    {
        try {
            return $this->router->dispatch($request);
        } catch (Throwable $e) {
            return $this->errorHandler->toResponse($e);
        }
    }
}
