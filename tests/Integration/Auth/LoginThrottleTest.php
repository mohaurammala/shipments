<?php

declare(strict_types=1);

namespace Tests\Integration\Auth;

use App\Core\Database;
use App\Repositories\LoginAttemptRepository;
use App\Services\LoginThrottle;
use PHPUnit\Framework\Attributes\Test;
use Tests\Integration\DatabaseTestCase;
use Tests\Support\FrozenClock;

final class LoginThrottleTest extends DatabaseTestCase
{
    private FrozenClock $clock;

    private LoginThrottle $throttle;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clock = new FrozenClock();
        $this->throttle = new LoginThrottle(new LoginAttemptRepository(Database::fromConnection($this->db)), $this->clock);
    }

    #[Test]
    public function theFifthFailureBlocksThatEmailFromThatAddress(): void
    {
        $this->recordFailures('a@example.test', '10.0.0.1', 4);
        self::assertFalse($this->throttle->isBlocked('a@example.test', '10.0.0.1'));

        $this->recordFailures('a@example.test', '10.0.0.1', 1);
        self::assertTrue($this->throttle->isBlocked('a@example.test', '10.0.0.1'));
    }

    #[Test]
    public function theBlockLiftsWhenTheWindowHasPassed(): void
    {
        $this->recordFailures('a@example.test', '10.0.0.1', 5);

        $this->clock->advance('+16 minutes');

        self::assertFalse($this->throttle->isBlocked('a@example.test', '10.0.0.1'));
    }

    #[Test]
    public function aSuccessfulSignInWipesTheSlate(): void
    {
        $this->recordFailures('a@example.test', '10.0.0.1', 4);
        $this->throttle->record('a@example.test', '10.0.0.1', true);
        $this->recordFailures('a@example.test', '10.0.0.1', 4);

        self::assertFalse($this->throttle->isBlocked('a@example.test', '10.0.0.1'));
    }

    #[Test]
    public function otherAccountsAndAddressesAreNotAffected(): void
    {
        $this->recordFailures('a@example.test', '10.0.0.1', 5);

        self::assertFalse($this->throttle->isBlocked('b@example.test', '10.0.0.1'));
        self::assertFalse($this->throttle->isBlocked('a@example.test', '10.0.0.2'));
    }

    #[Test]
    public function oneAddressTryingManyAccountsIsBlocked(): void
    {
        foreach (range(1, LoginThrottle::MAX_FAILURES_PER_ADDRESS) as $n) {
            $this->recordFailures("user{$n}@example.test", '10.0.0.9', 1);
        }

        self::assertTrue($this->throttle->isBlocked('someone-new@example.test', '10.0.0.9'));
    }

    private function recordFailures(string $email, string $ip, int $times): void
    {
        for ($i = 0; $i < $times; $i++) {
            $this->throttle->record($email, $ip, false);
        }
    }
}
