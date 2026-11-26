<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Enums\Role;
use App\Http\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Services\Authenticator;

/**
 * Lets only users with the given role through. Always listed after RequireLogin.
 */
final readonly class RequireRole implements Middleware
{
    public function __construct(
        private Authenticator $authenticator,
        private Role $role,
    ) {
    }

    public function process(Request $request, callable $next): Response
    {
        if ($this->authenticator->user()?->role !== $this->role) {
            throw new HttpException(403, 'You do not have access to that page.');
        }

        return $next($request);
    }
}
