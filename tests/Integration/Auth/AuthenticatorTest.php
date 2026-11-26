<?php

declare(strict_types=1);

namespace Tests\Integration\Auth;

use App\Core\Database;
use App\Domain\User;
use App\Repositories\UserRepository;
use App\Services\Authenticator;
use PHPUnit\Framework\Attributes\Test;
use Tests\Integration\DatabaseTestCase;
use Tests\Support\ArraySession;
use Tests\Support\FrozenClock;

/**
 * Each "request" is a fresh Authenticator over the same session, as in the real app.
 */
final class AuthenticatorTest extends DatabaseTestCase
{
    private ArraySession $session;

    private FrozenClock $clock;

    private UserRepository $users;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->session = new ArraySession();
        $this->clock = new FrozenClock();
        $this->users = new UserRepository(Database::fromConnection($this->db));
        $this->user = $this->users->findById($this->createUser()) ?? self::fail('User not created.');
    }

    #[Test]
    public function nobodyIsSignedInAtFirst(): void
    {
        self::assertNull($this->nextRequest()->user());
    }

    #[Test]
    public function signingInIssuesANewSessionIdAndIsRememberedOnTheNextRequest(): void
    {
        $this->nextRequest()->login($this->user);

        self::assertSame(1, $this->session->regenerations);
        self::assertSame($this->user->id, $this->nextRequest()->user()?->id);
    }

    #[Test]
    public function thirtyMinutesIdleSignsYouOut(): void
    {
        $this->nextRequest()->login($this->user);

        $this->clock->advance('+31 minutes');

        self::assertNull($this->nextRequest()->user());
        self::assertTrue($this->session->destroyed);
    }

    #[Test]
    public function activityKeepsTheSessionAlive(): void
    {
        $this->nextRequest()->login($this->user);

        $this->clock->advance('+20 minutes');
        self::assertNotNull($this->nextRequest()->user());

        $this->clock->advance('+20 minutes');
        self::assertNotNull($this->nextRequest()->user());
    }

    #[Test]
    public function eightHoursIsTheLimitEvenWhenActive(): void
    {
        $this->nextRequest()->login($this->user);

        for ($minutes = 25; $minutes <= 475; $minutes += 25) {
            $this->clock->advance('+25 minutes');
            self::assertNotNull($this->nextRequest()->user(), "Still signed in after {$minutes} minutes");
        }

        $this->clock->advance('+25 minutes');

        self::assertNull($this->nextRequest()->user());
    }

    #[Test]
    public function aDeactivatedUserIsSignedOutOnTheirNextRequest(): void
    {
        $this->nextRequest()->login($this->user);

        $this->users->setActive($this->user->id, false);

        self::assertNull($this->nextRequest()->user());
        self::assertTrue($this->session->destroyed);
    }

    #[Test]
    public function signingOutDestroysTheSession(): void
    {
        $authenticator = $this->nextRequest();
        $authenticator->login($this->user);

        $authenticator->logout();

        self::assertTrue($this->session->destroyed);
        self::assertNull($this->nextRequest()->user());
    }

    private function nextRequest(): Authenticator
    {
        return new Authenticator($this->session, $this->users, $this->clock);
    }
}
