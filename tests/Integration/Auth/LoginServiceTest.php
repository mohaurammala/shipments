<?php

declare(strict_types=1);

namespace Tests\Integration\Auth;

use App\Core\Database;
use App\Core\Logger;
use App\Domain\Role;
use App\Domain\User;
use App\Repositories\LoginAttemptRepository;
use App\Repositories\UserRepository;
use App\Services\Authenticator;
use App\Services\Enums\LoginResult;
use App\Services\LoginService;
use App\Services\LoginThrottle;
use App\Services\Passwords;
use PHPUnit\Framework\Attributes\Test;
use Tests\Integration\DatabaseTestCase;
use Tests\Support\ArraySession;
use Tests\Support\FrozenClock;

final class LoginServiceTest extends DatabaseTestCase
{
    private const string PASSWORD = 'correct horse battery';
    private const string IP = '172.19.0.1';

    private ArraySession $session;

    private UserRepository $users;

    private Authenticator $authenticator;

    private LoginService $login;

    /** @var resource */
    private $log;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $database = Database::fromConnection($this->db);
        $clock = new FrozenClock();
        $passwords = new Passwords();
        $log = fopen('php://memory', 'w+b');
        self::assertIsResource($log);
        $this->log = $log;

        $this->session = new ArraySession();
        $this->users = new UserRepository($database);
        $this->authenticator = new Authenticator($this->session, $this->users, $clock);
        $this->login = new LoginService(
            $this->users,
            $passwords,
            new LoginThrottle(new LoginAttemptRepository($database), $clock),
            $this->authenticator,
            new Logger($this->log),
            $clock,
        );
        $this->user = $this->users->create('thandi@example.test', $passwords->hash(self::PASSWORD), Role::Operator);
    }

    #[Test]
    public function theRightPasswordSignsYouIn(): void
    {
        self::assertSame(LoginResult::SignedIn, $this->login->attempt('thandi@example.test', self::PASSWORD, self::IP));
        self::assertSame($this->user->id, $this->authenticator->user()?->id);
        self::assertSame(1, $this->session->regenerations);
    }

    #[Test]
    public function signingInIsLoggedByUserIdNotByEmail(): void
    {
        $this->login->attempt('thandi@example.test', self::PASSWORD, self::IP);

        rewind($this->log);
        $log = (string) stream_get_contents($this->log);
        self::assertStringContainsString('"user_id":' . $this->user->id, $log);
        self::assertStringNotContainsString('thandi', $log);
    }

    #[Test]
    public function theEmailIsNotCaseSensitive(): void
    {
        self::assertSame(LoginResult::SignedIn, $this->login->attempt('  Thandi@Example.TEST ', self::PASSWORD, self::IP));
    }

    #[Test]
    public function aWrongPasswordAndAnUnknownEmailGetTheSameAnswer(): void
    {
        self::assertSame(LoginResult::InvalidCredentials, $this->login->attempt('thandi@example.test', 'wrong password!', self::IP));
        self::assertSame(LoginResult::InvalidCredentials, $this->login->attempt('nobody@example.test', self::PASSWORD, self::IP));
        self::assertNull($this->authenticator->user());
    }

    #[Test]
    public function aDeactivatedAccountIsRefusedLikeAWrongPassword(): void
    {
        $this->users->setActive($this->user->id, false);

        self::assertSame(LoginResult::InvalidCredentials, $this->login->attempt('thandi@example.test', self::PASSWORD, self::IP));
    }

    #[Test]
    public function afterFiveFailuresEvenTheRightPasswordIsTurnedAway(): void
    {
        for ($i = 0; $i < LoginThrottle::MAX_FAILURES_PER_ACCOUNT; $i++) {
            $this->login->attempt('thandi@example.test', 'wrong password!', self::IP);
        }

        self::assertSame(LoginResult::Throttled, $this->login->attempt('thandi@example.test', self::PASSWORD, self::IP));
        self::assertNull($this->authenticator->user());
    }

    #[Test]
    public function anOldBcryptHashIsUpgradedToArgon2idOnSignIn(): void
    {
        $this->users->updatePasswordHash($this->user->id, password_hash(self::PASSWORD, PASSWORD_BCRYPT, ['cost' => 4]));

        self::assertSame(LoginResult::SignedIn, $this->login->attempt('thandi@example.test', self::PASSWORD, self::IP));

        $stored = $this->users->findCredentials('thandi@example.test')['passwordHash'] ?? '';
        self::assertStringStartsWith('$argon2id$', $stored);
    }

    #[Test]
    public function spacesAroundAPasswordAreKept(): void
    {
        $this->users->updatePasswordHash($this->user->id, (new Passwords())->hash('  spaced out  '));

        self::assertSame(LoginResult::InvalidCredentials, $this->login->attempt('thandi@example.test', 'spaced out', self::IP));
        self::assertSame(LoginResult::SignedIn, $this->login->attempt('thandi@example.test', '  spaced out  ', self::IP));
    }
}
