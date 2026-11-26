<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Core\Interfaces\Session;

/**
 * An in-memory session that also records what happened to it, so tests can check that
 * sign-in issued a new session id and sign-out destroyed the session.
 */
final class ArraySession implements Session
{
    /** @var array<string, mixed> */
    public array $data = [];

    public int $regenerations = 0;

    public bool $destroyed = false;

    public function get(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function remove(string $key): void
    {
        unset($this->data[$key]);
    }

    public function regenerate(): void
    {
        $this->regenerations++;
    }

    public function destroy(): void
    {
        $this->data = [];
        $this->destroyed = true;
    }
}
