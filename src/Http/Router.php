<?php

declare(strict_types=1);

namespace App\Http;

use App\Http\Middleware\Middleware;
use InvalidArgumentException;

/**
 * Maps a method and path to a handler. Patterns use {name} for one path segment.
 */
final class Router
{
    private const string PARAM = '/\{([a-z_][a-z0-9_]*)\}/i';

    /**
     * @var list<array{method: string, regex: string, handler: callable(Request): Response, middleware: list<Middleware>}>
     */
    private array $routes = [];

    /**
     * @param callable(Request): Response $handler
     * @param list<Middleware> $middleware Outermost first.
     */
    public function add(string $method, string $pattern, callable $handler, array $middleware = []): void
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'regex' => $this->compile($pattern),
            'handler' => $handler,
            'middleware' => $middleware,
        ];
    }

    /**
     * @param callable(Request): Response $handler
     * @param list<Middleware> $middleware
     */
    public function get(string $pattern, callable $handler, array $middleware = []): void
    {
        $this->add('GET', $pattern, $handler, $middleware);
    }

    /**
     * @param callable(Request): Response $handler
     * @param list<Middleware> $middleware
     */
    public function post(string $pattern, callable $handler, array $middleware = []): void
    {
        $this->add('POST', $pattern, $handler, $middleware);
    }

    public function dispatch(Request $request): Response
    {
        // HEAD is GET without a body; Apache drops the body for us.
        $method = $request->method() === 'HEAD' ? 'GET' : $request->method();
        $allowed = [];

        foreach ($this->routes as $route) {
            if (preg_match($route['regex'], $request->path(), $matches) !== 1) {
                continue;
            }

            if ($route['method'] !== $method) {
                $allowed[] = $route['method'];
                continue;
            }

            $params = array_filter($matches, is_string(...), ARRAY_FILTER_USE_KEY);

            return $this->run($route['handler'], $route['middleware'], $request->withRouteParams($params));
        }

        if ($allowed !== []) {
            if (in_array('GET', $allowed, true)) {
                $allowed[] = 'HEAD';
            }

            throw HttpException::methodNotAllowed(array_values(array_unique($allowed)));
        }

        throw HttpException::notFound();
    }

    /**
     * @param callable(Request): Response $handler
     * @param list<Middleware> $middleware
     */
    private function run(callable $handler, array $middleware, Request $request): Response
    {
        $next = static fn (Request $request): Response => $handler($request);

        // Wrap from the inside out, so the first middleware listed runs first.
        foreach (array_reverse($middleware) as $layer) {
            $next = static fn (Request $request): Response => $layer->process($request, $next);
        }

        return $next($request);
    }

    /**
     * "/bookings/{id}/quote" becomes #^/bookings/(?P<id>[^/]+)/quote$#. Everything outside
     * {...} is matched literally, so a "." in a pattern is a dot, not "any character".
     */
    private function compile(string $pattern): string
    {
        if (!str_starts_with($pattern, '/')) {
            throw new InvalidArgumentException("Route patterns must start with '/': {$pattern}");
        }

        $parts = preg_split(self::PARAM, $pattern, -1, PREG_SPLIT_DELIM_CAPTURE);
        $regex = '';

        foreach ($parts === false ? [] : $parts as $index => $part) {
            // preg_split alternates: literal text at even positions, captured names at odd ones.
            $regex .= $index % 2 === 0 ? preg_quote($part, '#') : "(?P<{$part}>[^/]+)";
        }

        return '#^' . $regex . '$#';
    }
}
