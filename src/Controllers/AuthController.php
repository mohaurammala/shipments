<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\CsrfToken;
use App\Http\Pages;
use App\Http\Request;
use App\Http\Response;
use App\Services\Authenticator;
use App\Services\Enums\LoginResult;
use App\Services\LoginService;
use App\Services\LoginThrottle;

final readonly class AuthController
{
    public function __construct(
        private Pages $pages,
        private LoginService $login,
        private Authenticator $authenticator,
        private CsrfToken $csrf,
    ) {
    }

    public function showLogin(Request $request): Response
    {
        if ($this->authenticator->user() !== null) {
            return Response::redirect('/');
        }

        return $this->form();
    }

    public function login(Request $request): Response
    {
        $email = $request->input('email') ?? '';
        $result = $this->login->attempt($email, $request->secret('password') ?? '', $request->ip());

        return match ($result) {
            LoginResult::SignedIn => $this->signedIn(),
            LoginResult::InvalidCredentials => $this->form($email, 'The email or password is incorrect.', 422),
            LoginResult::Throttled => $this->form(
                $email,
                sprintf('Too many attempts. Wait %d minutes, then try again.', LoginThrottle::WINDOW_MINUTES),
                429,
            ),
        };
    }

    public function logout(Request $request): Response
    {
        $this->authenticator->logout();

        return Response::redirect('/login');
    }

    private function signedIn(): Response
    {
        $this->csrf->rotate();

        return Response::redirect('/');
    }

    /**
     * The email is kept so a typo in the password does not mean retyping it; the password
     * is never sent back.
     */
    private function form(string $email = '', ?string $error = null, int $status = 200): Response
    {
        return $this->pages->render('auth/login', ['title' => 'Sign in', 'email' => $email, 'error' => $error], $status);
    }
}
