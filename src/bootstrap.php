<?php

declare(strict_types=1);

/**
 * Composition root: the one place objects are created and connected. Every class
 * receives what it needs through its constructor, so nothing reaches for globals and each
 * piece can be tested on its own. Returns the Kernel that handles requests.
 */

use App\Controllers\HealthController;
use App\Core\Config;
use App\Core\Database;
use App\Core\Logger;
use App\Core\View;
use App\Http\ErrorHandler;
use App\Http\Kernel;
use App\Http\Router;
use Dotenv\Dotenv;

$root = dirname(__DIR__);

require $root . '/vendor/autoload.php';

// On hosts without Docker, .env supplies the configuration. Values Docker has already set
// always win (see Config::fromEnvironment), and safeLoad() does nothing if there is no .env.
Dotenv::createImmutable($root)->safeLoad();

$config = Config::fromEnvironment();
$logger = new Logger();

// Registered as early as possible: only reading the configuration it needs comes first.
// A malformed .env therefore fails before this point and is reported by PHP's own log.
$errors = new ErrorHandler($logger, new View($root . '/views'), $config->bool('APP_DEBUG', false));
$errors->register();

$database = new Database($config);

$health = new HealthController($database, $logger);

$router = new Router();
$router->get('/health', $health->show(...));

return new Kernel($router, $errors);
