<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Pages;
use App\Http\Request;
use App\Http\Response;
use App\Services\Enums\PasswordResetResult;
use App\Services\LoginThrottle;
use App\Services\PasswordResetService;
use App\Services\Passwords;

/**
 * Only routed when ADMIN_RESET_KEY is configured (see bootstrap.php).
 */
final readonly class PasswordResetController
{
    public function __construct(
        private Pages $pages,
        private PasswordResetService $resets,
        private Passwords $passwords,
    ) {
    }

    public function show(Request $request): Response
    {
        return $this->form();
    }

    public function reset(Request $request): Response
    {
        $email = $request->input('email') ?? '';
        $password = $request->secret('password') ?? '';

        if (!hash_equals($password, $request->secret('password_confirmation') ?? '')) {
            return $this->form($email, 'The two new passwords do not match.', 422);
        }

        $result = $this->resets->reset($request->secret('admin_key') ?? '', $email, $password, $request->ip());

        return match ($result) {
            // Post/Redirect/Get: the sign-in page shows the confirmation.
            PasswordResetResult::Reset => Response::redirect('/login?reset=done'),
            PasswordResetResult::InvalidKey => $this->form($email, 'The admin key is incorrect.', 422),
            PasswordResetResult::Throttled => $this->form(
                $email,
                sprintf('Too many attempts. Wait %d minutes, then try again.', LoginThrottle::WINDOW_MINUTES),
                429,
            ),
            PasswordResetResult::UnknownAccount => $this->form($email, 'No account uses that email address.', 422),
            PasswordResetResult::WeakPassword => $this->form($email, (string) $this->passwords->problemWith($password), 422),
        };
    }

    /**
     * Keeps the email after an error; the key and passwords are never sent back.
     */
    private function form(string $email = '', ?string $error = null, int $status = 200): Response
    {
        return $this->pages->render(
            'auth/reset-password',
            ['title' => 'Reset a password', 'email' => $email, 'error' => $error],
            $status,
        );
    }
}
