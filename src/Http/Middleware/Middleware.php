<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Request;
use App\Http\Response;

/**
 * Runs around a route's handler: it can inspect the request, stop it with its own
 * response (e.g. a redirect to /login), or call $next and adjust the response.
 */
interface Middleware
{
    /**
     * @param callable(Request): Response $next
     */
    public function process(Request $request, callable $next): Response;
}
