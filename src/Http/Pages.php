<?php

declare(strict_types=1);

namespace App\Http;

use App\Core\View;
use App\Services\Authenticator;

/**
 * Renders full pages with what every page needs: the signed-in user (for the header) and
 * the CSRF token (for every form, including the sign-out button).
 */
final readonly class Pages
{
    public function __construct(
        private View $view,
        private Authenticator $authenticator,
        private CsrfToken $csrf,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function render(string $template, array $data = [], int $status = 200): Response
    {
        $html = $this->view->render($template, [
            'currentUser' => $this->authenticator->user(),
            'csrfToken' => $this->csrf->value(),
            ...$data,
        ]);

        return Response::html($html, $status);
    }
}
