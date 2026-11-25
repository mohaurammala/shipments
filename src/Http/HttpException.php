<?php

declare(strict_types=1);

namespace App\Http;

use RuntimeException;

/**
 * An error the client caused (bad input, unknown page, wrong method).
 * The public message is safe to show; it never contains request data.
 */
final class HttpException extends RuntimeException
{
    /**
     * @param array<string, string> $headers Extra response headers, e.g. Allow for a 405.
     */
    public function __construct(
        public readonly int $status,
        public readonly string $publicMessage,
        public readonly array $headers = [],
    ) {
        parent::__construct($publicMessage, $status);
    }

    public static function badRequest(): self
    {
        return new self(400, 'The request could not be understood.');
    }

    public static function notFound(): self
    {
        return new self(404, 'The page you were looking for does not exist.');
    }

    /**
     * @param list<string> $allowed
     */
    public static function methodNotAllowed(array $allowed): self
    {
        return new self(405, 'That action is not available here.', ['Allow' => implode(', ', $allowed)]);
    }
}
