<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;
use App\Repositories\UserRepository;
use App\Services\Enums\PasswordResetResult;

/**
 * Demo convenience: anyone who knows ADMIN_RESET_KEY can set a new password for any
 * account. That makes the key a master key, so:
 *   - the page only exists when the key is configured, and never when APP_ENV=production;
 *   - the key must be long and random (MIN_KEY_LENGTH), and guesses are throttled;
 *   - the key is checked before anything else, so without it nothing about accounts leaks;
 *   - every reset is logged with the user id and the address it came from.
 */
final readonly class PasswordResetService
{
    public const int MIN_KEY_LENGTH = 32;

    /**
     * Wrong keys are counted in login_attempts under this name instead of an email, so the
     * existing throttle (5 per address per 15 minutes) covers key guessing too.
     */
    public const string THROTTLE_KEY = 'admin-reset-key';

    public function __construct(
        #[\SensitiveParameter] private string $adminKey,
        private UserRepository $users,
        private Passwords $passwords,
        private LoginThrottle $throttle,
        private Logger $logger,
    ) {
    }

    public function reset(
        #[\SensitiveParameter] string $adminKey,
        string $email,
        #[\SensitiveParameter] string $newPassword,
        string $ip,
    ): PasswordResetResult {
        if ($this->throttle->isBlocked(self::THROTTLE_KEY, $ip)) {
            return PasswordResetResult::Throttled;
        }

        if (!hash_equals($this->adminKey, $adminKey)) {
            $this->throttle->record(self::THROTTLE_KEY, $ip, false);
            $this->logger->warning('Admin reset key rejected', ['ip' => $ip]);

            return PasswordResetResult::InvalidKey;
        }

        $this->throttle->record(self::THROTTLE_KEY, $ip, true);

        if ($this->passwords->problemWith($newPassword) !== null) {
            return PasswordResetResult::WeakPassword;
        }

        $user = $this->users->findCredentials(LoginService::normaliseEmail($email))['user'] ?? null;

        if ($user === null) {
            return PasswordResetResult::UnknownAccount;
        }

        $this->users->updatePasswordHash($user->id, $this->passwords->hash($newPassword));
        $this->logger->warning('Password reset with the admin key', ['user_id' => $user->id, 'ip' => $ip]);

        return PasswordResetResult::Reset;
    }
}
