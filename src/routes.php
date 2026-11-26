<?php

declare(strict_types=1);

/**
 * Every URL the app answers, and who may reach it. Objects are built in bootstrap.php;
 * this file only connects paths to controller methods and middleware.
 */

use App\Controllers\AdminUserController;
use App\Controllers\AuthController;
use App\Controllers\HealthController;
use App\Controllers\HomeController;
use App\Controllers\PasswordResetController;
use App\Http\Middleware\RequireLogin;
use App\Http\Middleware\RequireRole;
use App\Http\Middleware\VerifyCsrfToken;
use App\Http\Router;

return static function (
    Router $router,
    RequireLogin $requireLogin,
    RequireRole $requireAdmin,
    VerifyCsrfToken $verifyCsrf,
    HealthController $health,
    AuthController $auth,
    HomeController $home,
    AdminUserController $adminUsers,
    ?PasswordResetController $resets,
): void {
    // Middleware sets, so each route says who may use it at a glance.
    $signedIn = [$requireLogin];
    $signedInWrite = [$requireLogin, $verifyCsrf];
    $admin = [$requireLogin, $requireAdmin];
    $adminWrite = [$requireLogin, $requireAdmin, $verifyCsrf];

    // Public
    $router->get('/health', $health->show(...));
    $router->get('/login', $auth->showLogin(...));
    $router->post('/login', $auth->login(...), [$verifyCsrf]);

    // Only when ADMIN_RESET_KEY is configured (bootstrap passes null otherwise).
    if (!is_null($resets)) {
        $router->get('/reset-password', $resets->show(...));
        $router->post('/reset-password', $resets->reset(...), [$verifyCsrf]);
    }

    // Signed in
    $router->post('/logout', $auth->logout(...), $signedInWrite);
    $router->get('/', $home->show(...), $signedIn);

    // Admins only: RequireLogin first (redirects to sign-in), then RequireRole (403).
    $router->get('/admin/users', $adminUsers->index(...), $admin);
    $router->get('/admin/users/new', $adminUsers->create(...), $admin);
    $router->post('/admin/users', $adminUsers->store(...), $adminWrite);
    $router->post('/admin/users/{id}/role', $adminUsers->changeRole(...), $adminWrite);
    $router->post('/admin/users/{id}/deactivate', $adminUsers->deactivate(...), $adminWrite);
    $router->post('/admin/users/{id}/reactivate', $adminUsers->reactivate(...), $adminWrite);
};
