<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Read-only application configuration, built once per request from the environment.
 */
final readonly class Config
{
    public function __construct(private array $values)
    {
    }

    public static function fromEnvironment(): self
    {
        $fromDotenv = array_filter($_ENV, is_string(...));

        return new self([...$fromDotenv, ...getenv()]);
    }

    public function get(string $key, ?string $default = null): string
    {
        return $this->raw($key) ?? $default ?? throw $this->missing($key);
    }

    public function int(string $key, ?int $default = null): int
    {
        $raw = $this->raw($key);

        if ($raw === null) {
            return $default ?? throw $this->missing($key);
        }

        $value = filter_var($raw, FILTER_VALIDATE_INT);

        if ($value === false) {
            throw new RuntimeException("Configuration value {$key} must be a whole number.");
        }

        return $value;
    }

    public function bool(string $key, ?bool $default = null): bool
    {
        $raw = $this->raw($key);

        if ($raw === null) {
            return $default ?? throw $this->missing($key);
        }

        $value = filter_var($raw, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

        if ($value === null) {
            throw new RuntimeException("Configuration value {$key} must be true or false.");
        }

        return $value;
    }

    /**
     * An empty value counts as missing, so "DB_PASSWORD=" never silently means "no password".
     */
    private function raw(string $key): ?string
    {
        $value = $this->values[$key] ?? null;

        return $value === null || $value === '' ? null : $value;
    }

    private function missing(string $key): RuntimeException
    {
        // Name the key, never a value: configuration values include secrets.
        return new RuntimeException("Missing configuration value: {$key}");
    }
}
