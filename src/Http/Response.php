<?php

declare(strict_types=1);

namespace App\Http;

use InvalidArgumentException;

/**
 * An immutable HTTP response. Controllers build one; only the front controller sends it.
 */
final readonly class Response
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        private string $body = '',
        private int $status = 200,
        private array $headers = [],
    ) {
    }

    public static function html(string $body, int $status = 200): self
    {
        return new self($body, $status, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    public static function text(string $body, int $status = 200): self
    {
        return new self($body, $status, ['Content-Type' => 'text/plain; charset=utf-8']);
    }

    /**
     * 303 See Other by default: after a POST, the browser follows up with a GET, so a
     * refresh never resubmits the form (Post/Redirect/Get).
     */
    public static function redirect(string $path, int $status = 303): self
    {
        if ($status < 300 || $status > 399) {
            throw new InvalidArgumentException('A redirect needs a 3xx status.');
        }

        if (!self::isLocalPath($path)) {
            throw new InvalidArgumentException('Redirects must stay on this site.');
        }

        return new self('', $status, ['Location' => $path]);
    }

    public function withHeader(string $name, string $value): static
    {
        if (preg_match('/[\r\n\0]/', $name . $value) === 1) {
            throw new InvalidArgumentException('Header names and values cannot contain line breaks.');
        }

        return clone($this, ['headers' => [...$this->headers, $name => $value]]);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function header(string $name): ?string
    {
        foreach ($this->headers as $key => $value) {
            if (strcasecmp($key, $name) === 0) {
                return $value;
            }
        }

        return null;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);

            foreach ($this->headers as $name => $value) {
                header("{$name}: {$value}");
            }
        }

        echo $this->body;
    }

    /**
     * Only paths on this site.
     */
    private static function isLocalPath(string $path): bool
    {
        return str_starts_with($path, '/')
            && !str_starts_with($path, '//')
            && !str_contains($path, '\\')
            && preg_match('/[\x00-\x1F\x7F]/', $path) === 0;
    }
}
