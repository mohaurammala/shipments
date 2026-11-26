<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Request;
use App\Http\Response;

/**
 * Browser-side protections on every response, error pages included (the Kernel runs this
 * outside its error handling).
 */
final readonly class SecurityHeaders implements Middleware
{
    /**
     * Only this site may supply scripts, styles, images, forms and frames. Bootstrap is
     * served from /assets, so no third-party origin is needed. form-action also applies to
     * where a form's redirect leads in some browsers;
     */
    private const string CONTENT_SECURITY_POLICY = "default-src 'self'; base-uri 'self'; form-action 'self'; "
        . "frame-ancestors 'none'; object-src 'none'";

    public function __construct(private bool $https)
    {
    }

    public function process(Request $request, callable $next): Response
    {
        $response = $next($request)
            ->withHeader('Content-Security-Policy', self::CONTENT_SECURITY_POLICY)
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('Referrer-Policy', 'same-origin')
            ->withHeader('X-Frame-Options', 'DENY');

        // Tells browsers to use HTTPS only, for a year. Only meaningful when served over HTTPS.
        return $this->https
            ? $response->withHeader('Strict-Transport-Security', 'max-age=31536000')
            : $response;
    }
}
