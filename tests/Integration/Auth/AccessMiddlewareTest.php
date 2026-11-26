<?php

declare(strict_types=1);

namespace Tests\Integration\Auth;

use App\Core\Database;
use App\Domain\Enums\Role;
use App\Http\HttpException;
use App\Http\Middleware\RequireLogin;
use App\Http\Middleware\RequireRole;
use App\Http\Request;
use App\Http\Response;
use App\Repositories\UserRepository;
use App\Services\Authenticator;
use PHPUnit\Framework\Attributes\Test;
use Tests\Integration\DatabaseTestCase;
use Tests\Support\ArraySession;
use Tests\Support\FrozenClock;

final class AccessMiddlewareTest extends DatabaseTestCase
{
    private UserRepository $users;

    private Authenticator $authenticator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->users = new UserRepository(Database::fromConnection($this->db));
        $this->authenticator = new Authenticator(new ArraySession(), $this->users, new FrozenClock());
    }

    #[Test]
    public function aVisitorWhoIsNotSignedInIsSentToSignIn(): void
    {
        $response = $this->through(new RequireLogin($this->authenticator));

        self::assertSame(303, $response->status());
        self::assertSame('/login', $response->header('Location'));
    }

    #[Test]
    public function aSignedInUserGetsThePageAndBrowsersAreToldNotToStoreIt(): void
    {
        $this->signIn('operator');

        $response = $this->through(new RequireLogin($this->authenticator));

        self::assertSame('page', $response->body());
        self::assertSame('no-store', $response->header('Cache-Control'));
    }

    #[Test]
    public function anOperatorCannotOpenAnAdminPage(): void
    {
        $this->signIn('operator');

        try {
            $this->through(new RequireRole($this->authenticator, Role::Admin));
            self::fail('Expected a 403.');
        } catch (HttpException $e) {
            self::assertSame(403, $e->status);
        }
    }

    #[Test]
    public function anAdminCanOpenAnAdminPage(): void
    {
        $this->signIn('admin');

        self::assertSame('page', $this->through(new RequireRole($this->authenticator, Role::Admin))->body());
    }

    private function signIn(string $role): void
    {
        $user = $this->users->findById($this->createUser("{$role}@example.test", $role)) ?? self::fail('User not created.');
        $this->authenticator->login($user);
    }

    private function through(RequireLogin|RequireRole $middleware): Response
    {
        return $middleware->process(new Request('GET', '/'), static fn (): Response => Response::text('page'));
    }
}
