<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Interfaces\Clock;
use App\Core\Interfaces\Session;
use App\Domain\User;
use App\Repositories\UserRepository;

/**
 * Who is signed in. The session stores only the user's id and two timestamps; the user is
 * loaded from the database on every request, so deactivating someone or changing their
 * role takes effect on their very next click.
 */
final class Authenticator
{
    public const int IDLE_TIMEOUT_SECONDS = 30 * 60;
    public const int ABSOLUTE_TIMEOUT_SECONDS = 8 * 60 * 60;

    private const string USER_ID = 'auth.user_id';
    private const string SIGNED_IN_AT = 'auth.signed_in_at';
    private const string LAST_SEEN_AT = 'auth.last_seen_at';

    private ?User $user = null;
    private bool $resolved = false;

    public function __construct(
        private readonly Session $session,
        private readonly UserRepository $users,
        private readonly Clock $clock,
    ) {
    }

    /**
     * The signed-in user, or null. Worked out once per request.
     */
    public function user(): ?User
    {
        if ($this->resolved) {
            return $this->user;
        }

        $this->resolved = true;
        $userId = $this->session->get(self::USER_ID);

        if (!is_int($userId)) {
            return null;
        }

        $now = $this->clock->now()->getTimestamp();
        $idleFor = $now - (int) $this->session->get(self::LAST_SEEN_AT);
        $signedInFor = $now - (int) $this->session->get(self::SIGNED_IN_AT);

        if ($idleFor > self::IDLE_TIMEOUT_SECONDS || $signedInFor > self::ABSOLUTE_TIMEOUT_SECONDS) {
            $this->logout();

            return null;
        }

        $user = $this->users->findById($userId);

        if ($user === null || !$user->isActive) {
            $this->logout();

            return null;
        }

        $this->session->set(self::LAST_SEEN_AT, $now);

        return $this->user = $user;
    }

    public function login(User $user): void
    {
        // A fresh session id, so one planted before sign-in is useless afterwards.
        $this->session->regenerate();

        $now = $this->clock->now()->getTimestamp();
        $this->session->set(self::USER_ID, $user->id);
        $this->session->set(self::SIGNED_IN_AT, $now);
        $this->session->set(self::LAST_SEEN_AT, $now);

        $this->user = $user;
        $this->resolved = true;
    }

    /**
     * A new session id for the signed-in user, e.g. after their own role changed, so an id
     * captured at the old privilege level stops working.
     */
    public function reissueSession(): void
    {
        $this->session->regenerate();
    }

    public function logout(): void
    {
        $this->session->destroy();
        $this->user = null;
        $this->resolved = true;
    }
}
