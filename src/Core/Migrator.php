<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Applies numbered .sql files in order, once each, recording them in schema_migrations.
 *
 * MariaDB commits CREATE and ALTER statements immediately, so a migration cannot be rolled
 * back. To keep a failure small and obvious: each file does one job, a version is recorded
 * only after all of its statements succeed, and the run stops at the first error.
 */
final readonly class Migrator
{
    private const string VERSION_PATTERN = '/^\d{3}_[a-z0-9_]+$/';

    public function __construct(
        private PDO $pdo,
        private string $directory,
    ) {
    }

    /**
     * @param (callable(string): void)|null $onApplied Called after each migration, e.g. to print progress.
     * @return list<string> The versions this run applied.
     */
    public function migrate(?callable $onApplied = null): array
    {
        $this->lock();

        try {
            $this->createTrackingTable();
            $alreadyApplied = $this->appliedVersions();
            $applied = [];

            foreach ($this->available() as $version => $path) {
                if (isset($alreadyApplied[$version])) {
                    continue;
                }

                $this->apply($version, $path);
                $applied[] = $version;

                if ($onApplied !== null) {
                    $onApplied($version);
                }
            }

            return $applied;
        } finally {
            $this->unlock();
        }
    }

    /**
     * Splits a migration file into statements. Rules for migration files: comments are on
     * their own lines starting with "--", and each statement ends with ";" at a line end.
     *
     * @return list<string>
     */
    public static function statements(string $sql): array
    {
        $withoutComments = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
        $parts = preg_split('/;\s*$/m', $withoutComments) ?: [];

        return array_values(array_filter(
            array_map(trim(...), $parts),
            static fn (string $statement): bool => $statement !== '',
        ));
    }

    private function apply(string $version, string $path): void
    {
        $sql = file_get_contents($path);

        if ($sql === false) {
            throw new RuntimeException("Cannot read migration {$version}.");
        }

        foreach (self::statements($sql) as $statement) {
            try {
                $this->pdo->exec($statement);
            } catch (PDOException $e) {
                throw new RuntimeException("Migration {$version} failed: {$e->getMessage()}", 0, $e);
            }
        }

        $this->pdo->prepare('INSERT INTO schema_migrations (version) VALUES (?)')->execute([$version]);
    }

    /**
     * @return array<string, string> Version => file path, in the order they must run.
     */
    private function available(): array
    {
        $files = glob("{$this->directory}/*.sql") ?: [];
        sort($files, SORT_STRING);

        $available = [];

        foreach ($files as $path) {
            $version = basename($path, '.sql');

            if (preg_match(self::VERSION_PATTERN, $version) !== 1) {
                throw new RuntimeException("Migration file names must look like 001_create_things.sql: {$version}");
            }

            $available[$version] = $path;
        }

        return $available;
    }

    /**
     * @return array<string, true>
     */
    private function appliedVersions(): array
    {
        $versions = $this->pdo->query('SELECT version FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);

        return array_fill_keys(array_map(strval(...), $versions), true);
    }

    private function createTrackingTable(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (
                version VARCHAR(255) NOT NULL,
                applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (version)
            ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_uca1400_ai_ci',
        );
    }

    /**
     * Two runs at once (say, two deploys) could both apply the same file. A named lock per
     * database makes the second run wait up to 10 seconds, then give up.
     */
    private function lock(): void
    {
        $acquired = $this->pdo->query("SELECT GET_LOCK(CONCAT(DATABASE(), '.migrations'), 10)")->fetchColumn();

        if ((int) $acquired !== 1) {
            throw new RuntimeException('Another migration run is in progress. Try again shortly.');
        }
    }

    private function unlock(): void
    {
        $this->pdo->query("SELECT RELEASE_LOCK(CONCAT(DATABASE(), '.migrations'))");
    }
}
