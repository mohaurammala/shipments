<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Config;
use App\Core\Database;
use App\Core\Migrator;
use App\Core\Uuid;
use LogicException;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

/**
 * Base for tests that need the real database. They run against DB_TEST_DATABASE, which is
 * migrated once per run. Each test runs inside a transaction that is rolled back
 * afterwards, so tests never see each other's rows and the test database stays empty.
 */
abstract class DatabaseTestCase extends TestCase
{
    private static ?PDO $connection = null;

    protected PDO $db;

    public static function connection(): PDO
    {
        if (self::$connection === null) {
            $config = Config::fromEnvironment();
            $testDatabase = $config->get('DB_TEST_DATABASE');

            // Rolled-back transactions are not a licence to touch real data.
            if ($testDatabase === $config->get('DB_DATABASE')) {
                throw new LogicException('DB_TEST_DATABASE must differ from DB_DATABASE.');
            }

            self::$connection = new Database($config, $testDatabase)->pdo();
            new Migrator(self::$connection, dirname(__DIR__, 2) . '/database/migrations')->migrate();
        }

        return self::$connection;
    }

    protected function setUp(): void
    {
        $this->db = self::connection();
        $this->db->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }

    protected function createUser(string $email = 'operator@example.test', string $role = 'operator'): int
    {
        $this->db->prepare('INSERT INTO users (email, password_hash, role) VALUES (?, ?, ?)')
            // The lowest bcrypt cost: fast tests, and these hashes protect nothing.
            ->execute([$email, password_hash('not-a-real-password', PASSWORD_BCRYPT, ['cost' => 4]), $role]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * @param array<string, mixed> $columns Overrides for the defaults below.
     */
    protected function createBooking(int $userId, array $columns = []): int
    {
        $row = [
            'public_id' => Uuid::v4(),
            'user_id' => $userId,
            'status' => 'DRAFT',
            'input_snapshot' => '{"parcels":[]}',
            'input_hash' => hash('sha256', '{"parcels":[]}'),
            ...$columns,
        ];

        $names = implode(', ', array_keys($row));
        $placeholders = implode(', ', array_fill(0, count($row), '?'));
        $this->db->prepare("INSERT INTO bookings ({$names}) VALUES ({$placeholders})")->execute(array_values($row));

        return (int) $this->db->lastInsertId();
    }

    protected function createRate(int $bookingId, string $amount = '125.50'): int
    {
        $this->db->prepare("INSERT INTO quotes (booking_id, input_hash, outcome) VALUES (?, ?, 'ok')")
            ->execute([$bookingId, hash('sha256', '{"parcels":[]}')]);
        $quoteId = (int) $this->db->lastInsertId();

        $this->db->prepare(
            "INSERT INTO quote_rates (quote_id, service_level_code, service_name, total_amount, vat_treatment)
             VALUES (?, 'ECO', 'Economy', ?, 'inclusive')",
        )->execute([$quoteId, $amount]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Asserts that the database refused a write, naming the constraint that refused it.
     *
     * @param callable(): mixed $write
     */
    protected function assertRefusedBy(string $constraint, callable $write): void
    {
        try {
            $write();
        } catch (PDOException $e) {
            self::assertStringContainsString($constraint, $e->getMessage());

            return;
        }

        self::fail("Expected the database to refuse the write ({$constraint}).");
    }
}
