<?php

declare(strict_types=1);

namespace App\Domain;

use App\Domain\Enums\Role;

/**
 * A person who can sign in. Deliberately has no password hash: the hash is only ever read
 * by the login check, so it cannot end up in a view, a log or a session by accident.
 */
final readonly class User
{
    public function __construct(
        public int $id,
        public string $email,
        public Role $role,
        public bool $isActive,
    ) {
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }
}
