<?php

declare(strict_types=1);

namespace Tests\Integration\Auth;

use App\Core\Database;
use App\Core\Logger;
use App\Domain\Enums\Role;
use App\Domain\User;
use App\Repositories\LoginAttemptRepository;
use App\Repositories\UserRepository;
use App\Services\Enums\PasswordResetResult;
use App\Services\LoginThrottle;
use App\Services\PasswordResetService;
use App\Services\Passwords;
use PHPUnit\Framework\Attributes\Test;
use Tests\Integration\DatabaseTestCase;
use Tests\Support\FrozenClock;

final class PasswordResetServiceTest extends DatabaseTestCase
{
    private const string KEY = '0123456789abcdef0123456789abcdef';
    private const string OLD_PASSWORD = 'the old password';
    private const string NEW_PASSWORD = 'a brand new password';
    private const string IP = '172.19.0.1';

    private UserRepository $users;

    private Passwords $passwords;

    private PasswordResetService $resets;

    /** @var resource */
    private $log;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $database = Database::fromConnection($this->db);
        $log = fopen('php://memory', 'w+b');
        self::assertIsResource($log);
        $this->log = $log;

        $this->users = new UserRepository($database);
        $this->passwords = new Passwords();
        $this->resets = new PasswordResetService(
            self::KEY,
            $this->users,
            $this->passwords,
            new LoginThrottle(new LoginAttemptRepository($database), new FrozenClock()),
            new Logger($this->log),
        );
        $this->user = $this->users->create('thandi@example.test', $this->passwords->hash(self::OLD_PASSWORD), Role::Operator);
    }

    #[Test]
    public function theRightKeySetsTheNewPassword(): void
    {
        $result = $this->resets->reset(self::KEY, 'Thandi@Example.test', self::NEW_PASSWORD, self::IP);

        self::assertSame(PasswordResetResult::Reset, $result);
        self::assertTrue($this->passwords->verify(self::NEW_PASSWORD, $this->storedHash()));
        self::assertFalse($this->passwords->verify(self::OLD_PASSWORD, $this->storedHash()));
    }

    #[Test]
    public function everyResetIsLoggedWithoutTheKeyOrPassword(): void
    {
        $this->resets->reset(self::KEY, 'thandi@example.test', self::NEW_PASSWORD, self::IP);

        rewind($this->log);
        $log = (string) stream_get_contents($this->log);
        self::assertStringContainsString('Password reset with the admin key', $log);
        self::assertStringContainsString('"user_id":' . $this->user->id, $log);
        self::assertStringNotContainsString(self::KEY, $log);
        self::assertStringNotContainsString(self::NEW_PASSWORD, $log);
    }

    #[Test]
    public function aWrongKeyChangesNothing(): void
    {
        $result = $this->resets->reset('not-the-key', 'thandi@example.test', self::NEW_PASSWORD, self::IP);

        self::assertSame(PasswordResetResult::InvalidKey, $result);
        self::assertTrue($this->passwords->verify(self::OLD_PASSWORD, $this->storedHash()));
    }

    #[Test]
    public function withoutTheKeyAnUnknownEmailLooksLikeAnyOtherAttempt(): void
    {
        self::assertSame(
            PasswordResetResult::InvalidKey,
            $this->resets->reset('not-the-key', 'nobody@example.test', self::NEW_PASSWORD, self::IP),
        );
    }

    #[Test]
    public function afterFiveWrongKeysEvenTheRightKeyIsTurnedAway(): void
    {
        for ($i = 0; $i < LoginThrottle::MAX_FAILURES_PER_ACCOUNT; $i++) {
            $this->resets->reset('not-the-key', 'thandi@example.test', self::NEW_PASSWORD, self::IP);
        }

        self::assertSame(
            PasswordResetResult::Throttled,
            $this->resets->reset(self::KEY, 'thandi@example.test', self::NEW_PASSWORD, self::IP),
        );
        self::assertTrue($this->passwords->verify(self::OLD_PASSWORD, $this->storedHash()));
    }

    #[Test]
    public function withTheKeyAnUnknownEmailIsReported(): void
    {
        self::assertSame(
            PasswordResetResult::UnknownAccount,
            $this->resets->reset(self::KEY, 'nobody@example.test', self::NEW_PASSWORD, self::IP),
        );
    }

    #[Test]
    public function theNewPasswordMustMeetThePasswordRules(): void
    {
        self::assertSame(
            PasswordResetResult::WeakPassword,
            $this->resets->reset(self::KEY, 'thandi@example.test', 'short', self::IP),
        );
        self::assertTrue($this->passwords->verify(self::OLD_PASSWORD, $this->storedHash()));
    }

    private function storedHash(): string
    {
        return $this->users->findCredentials('thandi@example.test')['passwordHash'] ?? '';
    }
}
