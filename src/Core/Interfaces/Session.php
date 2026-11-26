<?php

declare(strict_types=1);

namespace App\Core\Interfaces;

/**
 * Per-visitor storage between requests. An interface so tests can use an in-memory
 * version instead of PHP's native sessions, which need real cookies and headers.
 */
interface Session
{
    public function get(string $key): mixed;

    public function set(string $key, mixed $value): void;

    public function remove(string $key): void;

    /**
     * Issue a new session id, keeping the data. Done at sign-in, so an id an attacker
     * planted before sign-in (session fixation) becomes worthless.
     */
    public function regenerate(): void;

    /**
     * Delete all data and the cookie.
     */
    public function destroy(): void;
}
