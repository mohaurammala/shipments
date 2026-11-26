<?php

declare(strict_types=1);

/**
 * Creates the first admin account. There is no sign-up page: every other account is
 * created by an admin inside the app.
 *
 * Usage (inside the app container, interactively so the password prompt is hidden):
 *   docker compose exec app php bin/create-admin.php you@example.test
 */

use App\Core\Config;
use App\Core\Database;
use App\Domain\Enums\Role;
use App\Repositories\UserRepository;
use App\Services\LoginService;
use App\Services\Passwords;
use Dotenv\Dotenv;

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$root = dirname(__DIR__);

require $root . '/vendor/autoload.php';

Dotenv::createImmutable($root)->safeLoad();

/**
 * Reads one line. For a password, typing is not shown on screen when attached to a
 * terminal, and the value is never passed as an argument (arguments end up in shell
 * history and are visible to other users through the process list).
 */
$prompt = static function (string $label, bool $hidden = false): string {
    fwrite(STDOUT, $label);
    $hide = $hidden && stream_isatty(STDIN);

    if ($hide) {
        shell_exec('stty -echo');
    }

    try {
        $line = fgets(STDIN);
    } finally {
        if ($hide) {
            shell_exec('stty echo');
            fwrite(STDOUT, PHP_EOL);
        }
    }

    return rtrim($line === false ? '' : $line, "\r\n");
};

$fail = static function (string $message): never {
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
};

$email = LoginService::normaliseEmail($argv[1] ?? '');

if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    $fail('Usage: php bin/create-admin.php you@example.test');
}

$users = new UserRepository(new Database(Config::fromEnvironment()));
$passwords = new Passwords();

if ($users->emailExists($email)) {
    $fail("An account for {$email} already exists.");
}

$password = $prompt('Password: ', hidden: true);
$problem = $passwords->problemWith($password);

if ($problem !== null) {
    $fail($problem);
}

if (!hash_equals($password, $prompt('Repeat the password: ', hidden: true))) {
    $fail('The passwords do not match.');
}

$users->create($email, $passwords->hash($password), Role::Admin);

fwrite(STDOUT, "Admin account created for {$email}. Sign in at /login.\n");
