<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Domain\Enums\Role;
use App\Domain\User;
use App\Repositories\UserRepository;
use App\Services\Exceptions\UserManagementException;

/**
 * What admins can do to accounts, and the rules that keep the app usable:
 *   - an admin cannot deactivate their own account;
 *   - at least one active admin must always remain.
 * There is no delete: accounts own bookings, so they are deactivated instead.
 */
final readonly class UserService
{
    public function __construct(
        private Database $database,
        private UserRepository $users,
        private Passwords $passwords,
        private Logger $logger,
    ) {
    }

    /**
     * @throws UserManagementException When the email is already in use.
     */
    public function create(User $admin, string $email, #[\SensitiveParameter] string $password, Role $role): User
    {
        $email = LoginService::normaliseEmail($email);

        if ($this->users->emailExists($email)) {
            throw new UserManagementException('An account already uses that email address.');
        }

        $user = $this->users->create($email, $this->passwords->hash($password), $role);
        $this->logger->info('User created', ['user_id' => $user->id, 'role' => $role->value, 'by' => $admin->id]);

        return $user;
    }

    /**
     * @throws UserManagementException When this would leave no active admin.
     */
    public function changeRole(User $admin, User $target, Role $role): void
    {
        if ($target->role === $role) {
            return;
        }

        $this->database->transaction(function () use ($target, $role): void {
            if ($target->role === Role::Admin && $role !== Role::Admin) {
                $this->assertAnotherActiveAdminRemains($target);
            }

            $this->users->setRole($target->id, $role);
        });

        $this->logger->info('User role changed', ['user_id' => $target->id, 'role' => $role->value, 'by' => $admin->id]);
    }

    /**
     * @throws UserManagementException For the admin's own account, or the last active admin.
     */
    public function deactivate(User $admin, User $target): void
    {
        if ($target->id === $admin->id) {
            throw new UserManagementException('You cannot deactivate your own account.');
        }

        $this->database->transaction(function () use ($target): void {
            if ($target->role === Role::Admin) {
                $this->assertAnotherActiveAdminRemains($target);
            }

            $this->users->setActive($target->id, false);
        });

        $this->logger->info('User deactivated', ['user_id' => $target->id, 'by' => $admin->id]);
    }

    public function reactivate(User $admin, User $target): void
    {
        $this->users->setActive($target->id, true);
        $this->logger->info('User reactivated', ['user_id' => $target->id, 'by' => $admin->id]);
    }

    /**
     * Must run inside a transaction: the admin rows stay locked until it ends.
     */
    private function assertAnotherActiveAdminRemains(User $leaving): void
    {
        $others = array_diff($this->users->lockActiveAdminIds(), [$leaving->id]);

        if ($others === []) {
            throw new UserManagementException('At least one active admin must remain.');
        }
    }
}
