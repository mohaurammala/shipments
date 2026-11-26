<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Interfaces\Clock;
use App\Repositories\LoginAttemptRepository;
use DateInterval;

/**
 * Slows down password guessing without letting anyone lock a real user out for good:
 * a block lasts 15 minutes from the last failure, and blocked attempts are not recorded,
 * so hammering the form does not extend it.
 */
final readonly class LoginThrottle
{
    public const int WINDOW_MINUTES = 15;
    public const int MAX_FAILURES_PER_ACCOUNT = 5;
    public const int MAX_FAILURES_PER_ADDRESS = 20;

    public function __construct(
        private LoginAttemptRepository $attempts,
        private Clock $clock,
    ) {
    }

    public function isBlocked(string $email, string $ip): bool
    {
        $since = $this->clock->now()->sub(new DateInterval('PT' . self::WINDOW_MINUTES . 'M'));

        return $this->attempts->recentFailuresFor($email, $ip, $since) >= self::MAX_FAILURES_PER_ACCOUNT
            || $this->attempts->recentFailuresFrom($ip, $since) >= self::MAX_FAILURES_PER_ADDRESS;
    }

    public function record(string $email, string $ip, bool $succeeded): void
    {
        $this->attempts->record($email, $ip, $succeeded, $this->clock->now());
    }
}
