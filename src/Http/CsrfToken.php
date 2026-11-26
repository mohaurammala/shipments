<?php

declare(strict_types=1);

namespace App\Http;

use App\Core\Interfaces\Session;

/**
 * One secret per session, embedded in every form. A request forged by another site cannot
 * read our pages, so it cannot include the secret, and is refused.
 */
final readonly class CsrfToken
{
    public const string FIELD = '_token';

    private const string KEY = 'csrf.token';

    public function __construct(private Session $session)
    {
    }

    public function value(): string
    {
        $token = $this->session->get(self::KEY);

        if (!is_string($token)) {
            $token = $this->generate();
        }

        return $token;
    }

    /**
     * A new secret after sign-in, so a token seen before signing in is worthless after it.
     */
    public function rotate(): void
    {
        $this->generate();
    }

    public function matches(?string $submitted): bool
    {
        $token = $this->session->get(self::KEY);

        // hash_equals takes the same time however many characters match, so the secret
        // cannot be guessed one character at a time by measuring responses.
        return is_string($token) && $submitted !== null && hash_equals($token, $submitted);
    }

    private function generate(): string
    {
        $token = bin2hex(random_bytes(32));
        $this->session->set(self::KEY, $token);

        return $token;
    }
}
