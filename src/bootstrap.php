<?php

declare(strict_types=1);

/**
 * Composition root: the one place objects are created and connected. Every class
 * receives what it needs through its constructor, so nothing reaches for globals and each
 * piece can be tested on its own. Returns the Kernel that handles requests.
 */

use App\Controllers\AdminUserController;
use App\Controllers\AuthController;
use App\Controllers\BookingController;
use App\Controllers\HealthController;
use App\Controllers\LabelController;
use App\Controllers\PasswordResetController;
use App\Controllers\ShipmentController;
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
use App\Repositories\ApiCallRepository;
use App\Repositories\BookingRepository;
use App\Repositories\LabelRepository;
use App\Repositories\LoginAttemptRepository;
use App\Repositories\QuoteRepository;
use App\Repositories\UserRepository;
use App\Services\Authenticator;
use App\Services\BookingDraftService;
use App\Services\BookingService;
use App\Services\LabelService;
use App\Services\LoginService;
use App\Services\LoginThrottle;
use App\Services\PasswordResetService;
use App\Services\Passwords;
use App\Services\QuoteService;
use App\Services\UserService;
use App\Shiplogic\RatesRequestMapper;
use App\Shiplogic\ShiplogicClient;
use App\Shiplogic\ShipmentRequestMapper;
use App\Validation\ShipmentValidator;
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
// Bookings and the Shiplogic integration.
$isTestEnv = $config->get('APP_ENV', 'production') === 'sandbox';
$bookingRepository = new BookingRepository($database);
$labelRepository = new LabelRepository($database);
$shiplogic = ShiplogicClient::fromConfig($config, new ApiCallRepository($database), $logger);
$quotes = new QuoteService(
    $bookingRepository,
    new QuoteRepository($database),
    $shiplogic,
    new RatesRequestMapper(),
    $clock,
    $logger,
    ttlMinutes: $config->int('QUOTE_TTL_MINUTES', 30),
);
$bookingService = new BookingService(
    $bookingRepository,
    $quotes,
    $shiplogic,
    new ShipmentRequestMapper($isTestEnv),
    $clock,
    $logger,
    // The longest a booking request can take, plus a margin: after that, a booking still
    // in CREATING was abandoned by a request that died.
    stuckAfterSeconds: $config->int('SHIPLOGIC_CONNECT_TIMEOUT', 5) + $config->int('SHIPLOGIC_TIMEOUT', 20) + 30,
);
$bookings = new BookingController(
    $pages,
    $bookingRepository,
    new BookingDraftService($bookingRepository, $clock, $logger),
    $quotes,
    $bookingService,
    $labelRepository,
    new ShipmentValidator(),
    $authenticator,
    $flash,
    isTestEnvironment: $isTestEnv,
);
$shipments = new ShipmentController($bookingRepository, $bookingService, $authenticator, $flash);
$labels = new LabelController(
    $bookingRepository,
    new LabelService($labelRepository, $shiplogic, $logger),
    $authenticator,
    $flash,
);
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
    bookings: $bookings,
    shipments: $shipments,
    labels: $labels,
    adminUsers: $adminUsers,
    resets: $resets,
);

return new Kernel($router, $errors, [new SecurityHeaders($isHttps)]);
