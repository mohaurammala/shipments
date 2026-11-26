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

    /**
     * A redirect off this site, allowed only to https URLs on the given hosts or their
     * subdomains (e.g. ".shiplogic.com" for label PDFs). Even if an API answer were tampered
     * with, users could not be sent anywhere else. No referrer, no caching.
     *
     * @param list<string> $hostSuffixes Like ".shiplogic.com".
     */
    public static function redirectToTrustedHost(string $url, array $hostSuffixes): self
    {
        $parts = parse_url($url);
        $host = is_array($parts) ? strtolower($parts['host'] ?? '') : '';
        $trusted = array_filter(
            $hostSuffixes,
            static fn (string $suffix): bool => $host === ltrim($suffix, '.') || str_ends_with($host, $suffix),
        );

        if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || $trusted === [] || preg_match('/[\x00-\x20\x7F]/', $url) === 1) {
            throw new InvalidArgumentException('That redirect target is not trusted.');
        }

        return new self('', 303, ['Location' => $url, 'Cache-Control' => 'no-store', 'Referrer-Policy' => 'no-referrer']);
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
