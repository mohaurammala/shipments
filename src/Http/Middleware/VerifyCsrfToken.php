<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\CsrfToken;
use App\Http\HttpException;
use App\Http\Request;
use App\Http\Response;

/**
 * Refuses any request that changes something unless it carries this session's token.
 * GET requests never change anything, so they pass.
 */
final readonly class VerifyCsrfToken implements Middleware
{
    private const array SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public function __construct(private CsrfToken $token)
    {
    }

    public function process(Request $request, callable $next): Response
    {
        if (!in_array($request->method(), self::SAFE_METHODS, true)
            && !$this->token->matches($request->input(CsrfToken::FIELD))
        ) {
            throw new HttpException(403, 'This form has expired. Go back, refresh the page and try again.');
        }

        return $next($request);
    }
}
