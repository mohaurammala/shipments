<?php

declare(strict_types=1);

namespace App\Domain;

use DateTimeImmutable;

/**
 * A user plus the details only the admin list needs.
 */
final readonly class UserSummary
{
    public function __construct(
        public User $user,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $lastSignInAt,
    ) {
    }
}
