<?php

declare(strict_types=1);

/**
 * Composition root: the one place objects are created and connected. Every class
 * receives what it needs through its constructor, so nothing reaches for globals and each
 * piece can be tested on its own. Returns the Kernel that handles requests.
 */

use App\Controllers\AuthController;
use App\Controllers\HealthController;
use App\Controllers\HomeController;
use App\Core\Config;
use App\Core\Database;
use App\Core\Logger;
use App\Core\NativeSession;
use App\Core\SystemClock;
use App\Core\View;
use App\Http\CsrfToken;
use App\Http\ErrorHandler;
use App\Http\Kernel;
use App\Http\Middleware\RequireLogin;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\VerifyCsrfToken;
use App\Http\Pages;
use App\Http\Router;
use App\Repositories\LoginAttemptRepository;
use App\Repositories\UserRepository;
use App\Services\Authenticator;
use App\Services\LoginService;
use App\Services\LoginThrottle;
use App\Services\Passwords;
use Dotenv\Dotenv;

$root = dirname(__DIR__);

require $root . '/vendor/autoload.php';

// On hosts without Docker, .env supplies the configuration. Values Docker has already set
// always win (see Config::fromEnvironment), and safeLoad() does nothing if there is no .env.
Dotenv::createImmutable($root)->safeLoad();

$config = Config::fromEnvironment();
$logger = new Logger();
$view = new View($root . '/views');

// Registered as early as possible: only reading the configuration it needs comes first.
// A malformed .env therefore fails before this point and is reported by PHP's own log.
$errors = new ErrorHandler($logger, $view, $config->bool('APP_DEBUG', false));
$errors->register();

$isHttps = $config->bool('APP_HTTPS', false);
$clock = new SystemClock();
$database = new Database($config);
$session = new NativeSession($isHttps, Authenticator::IDLE_TIMEOUT_SECONDS);

$users = new UserRepository($database);
$authenticator = new Authenticator($session, $users, $clock);
$csrf = new CsrfToken($session);
$pages = new Pages($view, $authenticator, $csrf);

$login = new LoginService(
    $users,
    new Passwords(),
    new LoginThrottle(new LoginAttemptRepository($database), $clock),
    $authenticator,
    $logger,
    $clock,
);

// Middleware shared by routes. Pages need RequireLogin unless listed as public below;
// anything that changes data also needs VerifyCsrfToken.
$requireLogin = new RequireLogin($authenticator);
$verifyCsrf = new VerifyCsrfToken($csrf);

$health = new HealthController($database, $logger);
$auth = new AuthController($pages, $login, $authenticator, $csrf);
$home = new HomeController($pages);

$router = new Router();

// Public
$router->get('/health', $health->show(...));
$router->get('/login', $auth->showLogin(...));
$router->post('/login', $auth->login(...), [$verifyCsrf]);

// Signed in
$router->post('/logout', $auth->logout(...), [$requireLogin, $verifyCsrf]);
$router->get('/', $home->show(...), [$requireLogin]);

return new Kernel($router, $errors, [new SecurityHeaders($isHttps)]);
