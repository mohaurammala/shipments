<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Interfaces\Session;
use RuntimeException;

/**
 * PHP's own sessions, configured safely in code rather than relying on php.ini, and
 * started only when first used: /health and static files never create a session.
 */
final class NativeSession implements Session
{
    private bool $started = false;

    /**
     * @param int $lifetimeSeconds How long an unused session file is kept. Must be at least
     *                             the idle timeout, or PHP may delete a session the app
     *                             still considers valid.
     */
    public function __construct(
        private readonly bool $secureCookie,
        private readonly int $lifetimeSeconds,
    ) {
    }

    public function get(string $key): mixed
    {
        $this->start();

        return $_SESSION[$key] ?? null;
    }

    public function set(string $key, mixed $value): void
    {
        $this->start();
        $_SESSION[$key] = $value;
    }

    public function remove(string $key): void
    {
        $this->start();
        unset($_SESSION[$key]);
    }

    public function regenerate(): void
    {
        $this->start();
        session_regenerate_id(true);
    }

    public function destroy(): void
    {
        $this->start();
        $_SESSION = [];

        $cookie = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => 1,
            'path' => $cookie['path'],
            'secure' => $cookie['secure'],
            'httponly' => $cookie['httponly'],
            'samesite' => $cookie['samesite'],
        ]);

        session_destroy();
        $this->started = false;
    }

    private function start(): void
    {
        if ($this->started) {
            return;
        }

        // The __Host- prefix makes browsers refuse the cookie unless it is Secure, sent
        // for the whole site and not shared with subdomains. It needs HTTPS.
        session_name($this->secureCookie ? '__Host-shipments' : 'shipments');

        $started = session_start([
            'use_strict_mode' => true,
            'use_only_cookies' => true,
            'use_trans_sid' => false,
            'cookie_httponly' => true,
            'cookie_samesite' => 'Lax',
            'cookie_secure' => $this->secureCookie,
            'cookie_path' => '/',
            'cookie_lifetime' => 0,
            'gc_maxlifetime' => $this->lifetimeSeconds,
        ]);

        if (!$started) {
            throw new RuntimeException('The session could not be started.');
        }

        $this->started = true;
    }
}
