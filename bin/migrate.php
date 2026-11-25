<?php

declare(strict_types=1);

/**
 * Applies pending database migrations.
 *
 * Usage (inside the app container):
 *   php bin/migrate.php                  the app database (DB_DATABASE)
 *   php bin/migrate.php --database=test  the test database (DB_TEST_DATABASE)
 */

use App\Core\Config;
use App\Core\Database;
use App\Core\Migrator;
use Dotenv\Dotenv;

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$root = dirname(__DIR__);

require $root . '/vendor/autoload.php';

Dotenv::createImmutable($root)->safeLoad();

$options = getopt('', ['database:']);
$target = is_array($options) ? ($options['database'] ?? 'main') : 'main';

if (!in_array($target, ['main', 'test'], true)) {
    fwrite(STDERR, "Use --database=main or --database=test.\n");
    exit(2);
}

$config = Config::fromEnvironment();
$name = $config->get($target === 'test' ? 'DB_TEST_DATABASE' : 'DB_DATABASE');

try {
    $migrator = new Migrator(new Database($config, $name)->pdo(), $root . '/database/migrations');

    $applied = $migrator->migrate(static function (string $version): void {
        fwrite(STDOUT, "Applied {$version}\n");
    });
} catch (Throwable $e) {
    fwrite(STDERR, "Migration failed: {$e->getMessage()}\n");
    exit(1);
}

fwrite(STDOUT, $applied === []
    ? "Nothing to migrate: {$name} is up to date.\n"
    : sprintf("Done: %d migration(s) applied to %s.\n", count($applied), $name));
