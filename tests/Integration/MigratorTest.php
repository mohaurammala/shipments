<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Migrator;
use PDO;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Not a DatabaseTestCase: the migrator runs CREATE TABLE IF NOT EXISTS, which would
 * silently commit a test transaction.
 */
final class MigratorTest extends TestCase
{
    private const array TABLES = [
        'api_calls', 'bookings', 'labels', 'login_attempts', 'quote_rates', 'quotes', 'schema_migrations', 'users',
    ];

    #[Test]
    public function runningAgainAppliesNothing(): void
    {
        $pdo = DatabaseTestCase::connection();

        $applied = new Migrator($pdo, dirname(__DIR__, 2) . '/database/migrations')->migrate();

        self::assertSame([], $applied);
    }

    #[Test]
    public function everyMigrationIsRecordedAndEveryTableExists(): void
    {
        $pdo = DatabaseTestCase::connection();

        $versions = $pdo->query('SELECT version FROM schema_migrations ORDER BY version')->fetchAll(PDO::FETCH_COLUMN);
        $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        sort($tables);

        self::assertSame([
            '001_users', '002_login_attempts', '003_bookings', '004_quotes',
            '005_quote_rates', '006_labels', '007_api_calls',
        ], $versions);
        self::assertSame(self::TABLES, $tables);
    }
}
