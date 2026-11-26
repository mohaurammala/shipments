<?php

declare(strict_types=1);

namespace App\Http;

use App\Core\Interfaces\Session;

/**
 * One-time messages that survive a redirect: after a POST the browser is sent to a GET
 * page (Post/Redirect/Get), and that page shows what happened, once.
 */
final readonly class Flash
{
    private const string KEY = 'flash.messages';

    public function __construct(private Session $session)
    {
    }

    public function success(string $message): void
    {
        $this->add('success', $message);
    }

    public function error(string $message): void
    {
        $this->add('error', $message);
    }

    /**
     * Returns the messages and forgets them, so a refresh does not show them again.
     *
     * @return list<array{type: string, message: string}>
     */
    public function pull(): array
    {
        $messages = $this->session->get(self::KEY);

        if ($messages === null) {
            return [];
        }

        $this->session->remove(self::KEY);

        return is_array($messages) ? array_values($messages) : [];
    }

    private function add(string $type, string $message): void
    {
        $messages = $this->session->get(self::KEY);
        $messages = is_array($messages) ? $messages : [];
        $messages[] = ['type' => $type, 'message' => $message];

        $this->session->set(self::KEY, $messages);
    }
}
