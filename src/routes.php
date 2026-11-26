<?php

declare(strict_types=1);

/**
 * Every URL the app answers, and who may reach it. Objects are built in bootstrap.php;
 * this file only connects paths to controller methods and middleware.
 */

use App\Controllers\AdminUserController;
use App\Controllers\AuthController;
use App\Controllers\BookingController;
use App\Controllers\HealthController;
use App\Controllers\LabelController;
use App\Controllers\PasswordResetController;
use App\Controllers\ShipmentController;
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
    BookingController $bookings,
    ShipmentController $shipments,
    LabelController $labels,
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

    // Bookings. /bookings/new comes before /bookings/{id}: the first matching route wins,
    // and "new" would otherwise be read as a booking id.
    $router->get('/', $bookings->index(...), $signedIn);
    $router->get('/bookings/new', $bookings->create(...), $signedIn);
    $router->post('/bookings', $bookings->store(...), $signedInWrite);
    $router->get('/bookings/{id}', $bookings->show(...), $signedIn);
    $router->get('/bookings/{id}/edit', $bookings->edit(...), $signedIn);
    $router->post('/bookings/{id}/details', $bookings->update(...), $signedInWrite);
    $router->post('/bookings/{id}/quote', $bookings->quote(...), $signedInWrite);
    $router->post('/bookings/{id}/select', $bookings->select(...), $signedInWrite);
    $router->post('/bookings/{id}/book', $shipments->book(...), $signedInWrite);
    $router->post('/bookings/{id}/cancel', $shipments->cancel(...), $signedInWrite);
    // A GET link opened in a new tab: it changes nothing about the booking (see LabelController).
    $router->get('/bookings/{id}/label', $labels->open(...), $signedIn);
    $router->post('/bookings/{id}/resolve', $shipments->resolve(...), $adminWrite);

    // Admins only: RequireLogin first (redirects to sign-in), then RequireRole (403).
    $router->get('/admin/users', $adminUsers->index(...), $admin);
    $router->get('/admin/users/new', $adminUsers->create(...), $admin);
    $router->post('/admin/users', $adminUsers->store(...), $adminWrite);
    $router->post('/admin/users/{id}/role', $adminUsers->changeRole(...), $adminWrite);
    $router->post('/admin/users/{id}/deactivate', $adminUsers->deactivate(...), $adminWrite);
    $router->post('/admin/users/{id}/reactivate', $adminUsers->reactivate(...), $adminWrite);
};
