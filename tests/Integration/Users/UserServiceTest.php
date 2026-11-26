<?php

declare(strict_types=1);

namespace Tests\Integration\Users;

use App\Core\Database;
use App\Core\Logger;
use App\Domain\Enums\Role;
use App\Domain\User;
use App\Repositories\UserRepository;
use App\Services\Exceptions\UserManagementException;
use App\Services\Passwords;
use App\Services\UserService;
use PHPUnit\Framework\Attributes\Test;
use Tests\Integration\DatabaseTestCase;

final class UserServiceTest extends DatabaseTestCase
{
    private UserRepository $users;

    private UserService $service;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $database = Database::fromConnection($this->db);
        $log = fopen('php://memory', 'w+b');
        self::assertIsResource($log);

        $this->users = new UserRepository($database);
        $this->service = new UserService($database, $this->users, new Passwords(), new Logger($log));
        $this->admin = $this->user($this->createUser('admin@example.test', 'admin'));
    }

    #[Test]
    public function anAdminCreatesAnAccountThatCanBeFoundByItsEmail(): void
    {
        $created = $this->service->create($this->admin, ' Sipho@Example.TEST ', 'a perfectly long password', Role::Operator);

        self::assertSame('sipho@example.test', $created->email);
        self::assertSame(Role::Operator, $this->user($created->id)->role);
        self::assertStringStartsWith('$argon2id$', $this->users->findCredentials('sipho@example.test')['passwordHash'] ?? '');
    }

    #[Test]
    public function anEmailThatIsAlreadyUsedIsRefusedWhateverItsCase(): void
    {
        $this->expectException(UserManagementException::class);

        $this->service->create($this->admin, 'ADMIN@example.test', 'a perfectly long password', Role::Operator);
    }

    #[Test]
    public function anAdminCannotDeactivateThemselves(): void
    {
        $this->expectExceptionObject(new UserManagementException('You cannot deactivate your own account.'));

        $this->service->deactivate($this->admin, $this->admin);
    }

    #[Test]
    public function anOperatorCanBeDeactivatedAndReactivated(): void
    {
        $operator = $this->user($this->createUser());

        $this->service->deactivate($this->admin, $operator);
        self::assertFalse($this->user($operator->id)->isActive);

        $this->service->reactivate($this->admin, $operator);
        self::assertTrue($this->user($operator->id)->isActive);
    }

    #[Test]
    public function theLastActiveAdminCannotBeDemoted(): void
    {
        try {
            $this->service->changeRole($this->admin, $this->admin, Role::Operator);
            self::fail('Expected the last admin to be protected.');
        } catch (UserManagementException $e) {
            self::assertSame('At least one active admin must remain.', $e->getMessage());
        }

        self::assertSame(Role::Admin, $this->user($this->admin->id)->role);
    }

    #[Test]
    public function aDeactivatedAdminDoesNotCountAsTheOneThatRemains(): void
    {
        $other = $this->user($this->createUser('other-admin@example.test', 'admin'));
        $this->service->deactivate($this->admin, $other);

        $this->expectException(UserManagementException::class);

        $this->service->changeRole($this->admin, $this->admin, Role::Operator);
    }

    #[Test]
    public function anAdminCanStepDownWhenAnotherActiveAdminRemains(): void
    {
        $this->createUser('other-admin@example.test', 'admin');

        $this->service->changeRole($this->admin, $this->admin, Role::Operator);

        self::assertSame(Role::Operator, $this->user($this->admin->id)->role);
    }

    #[Test]
    public function anAdminCanPromoteAnOperator(): void
    {
        $operator = $this->user($this->createUser());

        $this->service->changeRole($this->admin, $operator, Role::Admin);

        self::assertSame(Role::Admin, $this->user($operator->id)->role);
    }

    #[Test]
    public function theAdminListShowsEveryAccountWithItsLastSignIn(): void
    {
        $this->createUser('zola@example.test');

        $summaries = $this->users->summaries();

        self::assertSame(['admin@example.test', 'zola@example.test'], array_map(
            static fn ($summary): string => $summary->user->email,
            $summaries,
        ));
        self::assertNull($summaries[0]->lastSignInAt);
    }

    private function user(int $id): User
    {
        return $this->users->findById($id) ?? self::fail("User {$id} not found.");
    }
}
