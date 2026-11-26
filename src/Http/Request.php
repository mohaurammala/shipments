<?php

declare(strict_types=1);

namespace App\Http;

/**
 * The only place the app reads request data. Values are normalised here (trimmed, valid
 * UTF-8, bounded in size and shape) but never escaped: escaping happens on output.
 */
final readonly class Request
{
    public const int MAX_FIELD_BYTES = 10_000;
    public const int MAX_ARRAY_ITEMS = 100;
    public const int MAX_ARRAY_DEPTH = 3;

    /**
     * @param array<array-key, mixed> $query Raw query string values ($_GET).
     * @param array<array-key, mixed> $body Raw form values ($_POST).
     * @param array<string, string> $routeParams Values captured by the router, e.g. {id}.
     */
    public function __construct(
        private string $method,
        private string $path,
        private array $query = [],
        private array $body = [],
        private string $ip = '',
        private array $routeParams = [],
    ) {
    }

    public static function fromGlobals(): self
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = explode('?', $uri, 2)[0];

        return new self(
            method: strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')),
            path: $path === '' ? '/' : $path,
            query: $_GET,
            body: $_POST,
            ip: (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
        );
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    /**
     * The direct client address. X-Forwarded-For is ignored: any client can set it.
     */
    public function ip(): string
    {
        return $this->ip;
    }

    public function query(string $key): ?string
    {
        return $this->single($this->query, $key);
    }

    public function input(string $key): ?string
    {
        return $this->single($this->body, $key);
    }

    /**
     * A password or other secret: checked like input() but never trimmed, because a space
     * at either end is part of the secret the user chose.
     */
    public function secret(string $key): ?string
    {
        return $this->single($this->body, $key, trim: false);
    }

    /**
     * A nested form value such as parcels[0][weight_kg], with every leaf normalised.
     *
     * @return array<array-key, mixed>
     */
    public function inputArray(string $key): array
    {
        if (!array_key_exists($key, $this->body)) {
            return [];
        }

        $value = $this->body[$key];

        if (!is_array($value)) {
            throw HttpException::badRequest();
        }

        return $this->normaliseArray($value, 1);
    }

    public function routeParam(string $name): ?string
    {
        return $this->routeParams[$name] ?? null;
    }

    /**
     * @param array<string, string> $params
     */
    public function withRouteParams(array $params): static
    {
        return clone($this, ['routeParams' => $params]);
    }

    /**
     * @param array<array-key, mixed> $source
     */
    private function single(array $source, string $key, bool $trim = true): ?string
    {
        if (!array_key_exists($key, $source)) {
            return null;
        }

        $value = $source[$key];

        // A form never sends field[]=... for a single field, so an array here is tampering.
        if (!is_string($value)) {
            throw HttpException::badRequest();
        }

        return $this->normalise($value, $trim);
    }

    private function normalise(string $value, bool $trim = true): string
    {
        if (
            strlen($value) > self::MAX_FIELD_BYTES
            || !mb_check_encoding($value, 'UTF-8')
            || str_contains($value, "\0")
        ) {
            throw HttpException::badRequest();
        }

        return $trim ? trim($value) : $value;
    }

    /**
     * @param array<array-key, mixed> $items
     * @return array<array-key, mixed>
     */
    private function normaliseArray(array $items, int $depth): array
    {
        if ($depth > self::MAX_ARRAY_DEPTH || count($items) > self::MAX_ARRAY_ITEMS) {
            throw HttpException::badRequest();
        }

        $normalised = [];

        foreach ($items as $key => $value) {
            $normalised[$key] = match (true) {
                is_array($value) => $this->normaliseArray($value, $depth + 1),
                is_string($value) => $this->normalise($value),
                default => throw HttpException::badRequest(),
            };
        }

        return $normalised;
    }
}
