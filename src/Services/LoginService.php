<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Interfaces\Clock;
use App\Core\Logger;
use App\Repositories\UserRepository;
use App\Services\Enums\LoginResult;

/**
 * The sign-in decision: throttle, check the password, upgrade an old hash, sign in.
 */
final readonly class LoginService
{
    public function __construct(
        private UserRepository $users,
        private Passwords $passwords,
        private LoginThrottle $throttle,
        private Authenticator $authenticator,
        private Logger $logger,
        private Clock $clock,
    ) {
    }

    public function attempt(string $email, #[\SensitiveParameter] string $password, string $ip): LoginResult
    {
        $email = self::normaliseEmail($email);

        // Checked before the password, so a blocked guesser learns nothing more.
        if ($this->throttle->isBlocked($email, $ip)) {
            $this->logger->warning('Sign-in throttled', ['ip' => $ip]);

            return LoginResult::Throttled;
        }

        $credentials = $email === '' ? null : $this->users->findCredentials($email);

        // Always runs a full hash check (against a dummy hash when no account matched), so a
        // wrong email takes as long as a wrong password.
        $passwordMatches = $this->passwords->verify($password, $credentials['passwordHash'] ?? null);
        $user = $credentials['user'] ?? null;

        if (!$passwordMatches || $user === null || !$user->isActive) {
            $this->throttle->record($email, $ip, false);

            return LoginResult::InvalidCredentials;
        }

        if ($this->passwords->needsRehash($credentials['passwordHash'])) {
            $this->users->updatePasswordHash($user->id, $this->passwords->hash($password));
        }

        $this->throttle->record($email, $ip, true);
        $this->users->recordSignIn($user->id, $this->clock->now());
        $this->authenticator->login($user);
        $this->logger->info('User signed in', ['user_id' => $user->id]);

        return LoginResult::SignedIn;
    }

    /**
     * Emails are stored and compared in lower case, so "Thandi@Example.test" and
     * "thandi@example.test" are one account and one throttle counter.
     */
    public static function normaliseEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}
