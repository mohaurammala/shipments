<?php

declare(strict_types=1);

/**
 * Composition root: the one place objects are created and connected. Every class
 * receives what it needs through its constructor, so nothing reaches for globals and each
 * piece can be tested on its own. Returns the Kernel that handles requests.
 */

use App\Controllers\AdminUserController;
use App\Controllers\AuthController;
use App\Controllers\HealthController;
use App\Controllers\HomeController;
use App\Controllers\PasswordResetController;
use App\Core\Config;
use App\Core\Database;
use App\Core\Logger;
use App\Core\NativeSession;
use App\Core\SystemClock;
use App\Core\View;
use App\Domain\Enums\Role;
use App\Http\CsrfToken;
use App\Http\ErrorHandler;
use App\Http\Flash;
use App\Http\Kernel;
use App\Http\Middleware\RequireLogin;
use App\Http\Middleware\RequireRole;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\VerifyCsrfToken;
use App\Http\Pages;
use App\Http\Router;
use App\Repositories\LoginAttemptRepository;
use App\Repositories\UserRepository;
use App\Services\Authenticator;
use App\Services\LoginService;
use App\Services\LoginThrottle;
use App\Services\PasswordResetService;
use App\Services\Passwords;
use App\Services\UserService;
use App\Validation\UserValidator;
use Dotenv\Dotenv;

date_default_timezone_set('Africa/Johannesburg');

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
$flash = new Flash($session);
$pages = new Pages($view, $authenticator, $csrf, $flash);

$passwords = new Passwords();
$throttle = new LoginThrottle(new LoginAttemptRepository($database), $clock);
$login = new LoginService($users, $passwords, $throttle, $authenticator, $logger, $clock);

// Demo convenience: /reset-password lets anyone with ADMIN_RESET_KEY set any account's
// password. It exists only when the key is set, and never when APP_ENV is production.
$adminResetKey = $config->get('APP_ENV', 'production') === 'production'
    ? '' : $config->get('ADMIN_RESET_KEY', '');
$passwordResetEnabled = $adminResetKey !== '';

if ($passwordResetEnabled && strlen($adminResetKey) < PasswordResetService::MIN_KEY_LENGTH) {
    throw new RuntimeException(sprintf(
        'ADMIN_RESET_KEY must be at least %d characters. Generate one with: openssl rand -hex 32',
        PasswordResetService::MIN_KEY_LENGTH,
    ));
}

// Middleware shared by routes. Pages need RequireLogin unless listed as public below;
// anything that changes data also needs VerifyCsrfToken.
$requireLogin = new RequireLogin($authenticator);
$requireAdmin = new RequireRole($authenticator, Role::Admin);
$verifyCsrf = new VerifyCsrfToken($csrf);

$health = new HealthController($database, $logger);
$auth = new AuthController($pages, $login, $authenticator, $csrf, $passwordResetEnabled);
$home = new HomeController($pages);
$adminUsers = new AdminUserController(
    $pages,
    new UserService($database, $users, $passwords, $logger),
    $users,
    new UserValidator($passwords),
    $authenticator,
    $flash,
);

$resets = $passwordResetEnabled ? new PasswordResetController(
        $pages,
        new PasswordResetService($adminResetKey, $users, $passwords, $throttle, $logger),
        $passwords,
    )
    : null;

$router = new Router();
$registerRoutes = require __DIR__ . '/routes.php';
$registerRoutes(
    router: $router,
    requireLogin: $requireLogin,
    requireAdmin: $requireAdmin,
    verifyCsrf: $verifyCsrf,
    health: $health,
    auth: $auth,
    home: $home,
    adminUsers: $adminUsers,
    resets: $resets,
);

return new Kernel($router, $errors, [new SecurityHeaders($isHttps)]);
