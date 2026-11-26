<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Request;
use App\Http\Response;
use App\Services\Authenticator;

/**
 * Sends visitors who are not signed in to the sign-in page. Pages behind it hold personal
 * data, so browsers and proxies are told never to store them.
 */
final readonly class RequireLogin implements Middleware
{
    public function __construct(private Authenticator $authenticator)
    {
    }

    public function process(Request $request, callable $next): Response
    {
        if ($this->authenticator->user() === null) {
            return Response::redirect('/login');
        }

        return $next($request)->withHeader('Cache-Control', 'no-store');
    }
}
