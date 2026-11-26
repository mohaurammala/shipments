<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Domain\Enums\Role;
use App\Domain\User;
use App\Domain\UserSummary;
use DateTimeImmutable;
use DateTimeZone;
use PDO;

final readonly class UserRepository
{
    private const string COLUMNS = 'id, email, role, is_active';

    public function __construct(private Database $database)
    {
    }

    public function findById(int $id): ?User
    {
        $statement = $this->database->pdo()->prepare('SELECT ' . self::COLUMNS . ' FROM users WHERE id = ?');
        $statement->execute([$id]);
        $row = $statement->fetch();

        return $row === false ? null : $this->toUser($row);
    }

    /**
     * The one place the password hash is read, for checking a sign-in.
     *
     * @return array{user: User, passwordHash: string}|null
     */
    public function findCredentials(string $email): ?array
    {
        $statement = $this->database->pdo()->prepare(
            'SELECT ' . self::COLUMNS . ', password_hash FROM users WHERE email = ?',
        );
        $statement->execute([$email]);
        $row = $statement->fetch();

        return $row === false ? null : ['user' => $this->toUser($row), 'passwordHash' => (string) $row['password_hash']];
    }

    public function emailExists(string $email): bool
    {
        $statement = $this->database->pdo()->prepare('SELECT 1 FROM users WHERE email = ?');
        $statement->execute([$email]);

        return $statement->fetchColumn() !== false;
    }

    public function create(string $email, string $passwordHash, Role $role): User
    {
        $pdo = $this->database->pdo();
        $pdo->prepare('INSERT INTO users (email, password_hash, role) VALUES (?, ?, ?)')
            ->execute([$email, $passwordHash, $role->value]);

        return new User((int) $pdo->lastInsertId(), $email, $role, true);
    }

    public function updatePasswordHash(int $userId, string $passwordHash): void
    {
        $this->database->pdo()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
            ->execute([$passwordHash, $userId]);
    }

    public function recordSignIn(int $userId, DateTimeImmutable $at): void
    {
        $this->database->pdo()->prepare('UPDATE users SET last_login_at = ? WHERE id = ?')
            ->execute([$at->format('Y-m-d H:i:s'), $userId]);
    }

    public function setActive(int $userId, bool $active): void
    {
        $this->database->pdo()->prepare('UPDATE users SET is_active = ? WHERE id = ?')
            ->execute([$active ? 1 : 0, $userId]);
    }

    public function setRole(int $userId, Role $role): void
    {
        $this->database->pdo()->prepare('UPDATE users SET role = ? WHERE id = ?')
            ->execute([$role->value, $userId]);
    }

    /**
     * @return list<UserSummary> Every account, by email.
     */
    public function summaries(): array
    {
        $rows = $this->database->pdo()
            ->query('SELECT ' . self::COLUMNS . ', created_at, last_login_at FROM users ORDER BY email')
            ->fetchAll();
        $utc = new DateTimeZone('UTC');

        return array_map(fn (array $row): UserSummary => new UserSummary(
            $this->toUser($row),
            new DateTimeImmutable((string) $row['created_at'], $utc),
            $row['last_login_at'] === null ? null : new DateTimeImmutable((string) $row['last_login_at'], $utc),
        ), $rows);
    }

    /**
     * The active admins' ids, with their rows locked until the surrounding transaction ends.
     * A second request checking "is another admin left?" waits here instead of reading the
     * same answer, so two admins cannot demote each other at the same moment and leave none.
     *
     * @return list<int>
     */
    public function lockActiveAdminIds(): array
    {
        $statement = $this->database->pdo()->prepare(
            'SELECT id FROM users WHERE role = ? AND is_active = 1 FOR UPDATE',
        );
        $statement->execute([Role::Admin->value]);

        return array_map(intval(...), $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * @param array<string, mixed> $row
     */
    private function toUser(array $row): User
    {
        return new User(
            id: (int) $row['id'],
            email: (string) $row['email'],
            role: Role::from((string) $row['role']),
            isActive: (bool) $row['is_active'],
        );
    }
}
