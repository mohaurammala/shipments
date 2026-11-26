<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Not a DatabaseTestCase: these tests need to start and finish real transactions.
 */
final class DatabaseTransactionTest extends TestCase
{
    private const string EMAIL = 'transaction-test@example.test';

    protected function tearDown(): void
    {
        // Only matters if a test fails: never leave a row behind in the test database.
        DatabaseTestCase::connection()->prepare('DELETE FROM users WHERE email = ?')->execute([self::EMAIL]);
    }

    #[Test]
    public function workThatThrowsIsRolledBack(): void
    {
        $database = Database::fromConnection(DatabaseTestCase::connection());

        try {
            $database->transaction(function () use ($database): void {
                $this->insertUser($database);

                throw new RuntimeException('Something failed half-way.');
            });
        } catch (RuntimeException) {
        }

        self::assertSame(0, $this->countUsers($database));
    }

    #[Test]
    public function workThatReturnsIsCommittedAndItsValueReturned(): void
    {
        $database = Database::fromConnection(DatabaseTestCase::connection());

        $result = $database->transaction(function () use ($database): string {
            $this->insertUser($database);

            return 'done';
        });

        self::assertSame('done', $result);
        self::assertSame(1, $this->countUsers($database));
    }

    #[Test]
    public function insideAnOpenTransactionItJoinsInsteadOfStartingAnother(): void
    {
        $database = Database::fromConnection(DatabaseTestCase::connection());
        $database->pdo()->beginTransaction();

        try {
            $database->transaction(fn () => $this->insertUser($database));
            self::assertTrue($database->pdo()->inTransaction(), 'The outer transaction must still be open.');
        } finally {
            $database->pdo()->rollBack();
        }

        self::assertSame(0, $this->countUsers($database));
    }

    private function insertUser(Database $database): void
    {
        $database->pdo()->prepare("INSERT INTO users (email, password_hash, role) VALUES (?, 'x', 'operator')")
            ->execute([self::EMAIL]);
    }

    private function countUsers(Database $database): int
    {
        $statement = $database->pdo()->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
        $statement->execute([self::EMAIL]);

        return (int) $statement->fetchColumn();
    }
}
