<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use DateTimeImmutable;

final readonly class LoginAttemptRepository
{
    public function __construct(private Database $database)
    {
    }

    public function record(string $email, string $ip, bool $succeeded, DateTimeImmutable $at): void
    {
        $this->database->pdo()
            ->prepare('INSERT INTO login_attempts (email, ip, succeeded, attempted_at) VALUES (?, ?, ?, ?)')
            ->execute([$email, $ip, $succeeded ? 1 : 0, $at->format('Y-m-d H:i:s')]);
    }

    /**
     * Failures for this email from this address since $since, not counting any before the
     * most recent success: a correct sign-in wipes the slate.
     * Served by the (email, ip, attempted_at) index.
     */
    public function recentFailuresFor(string $email, string $ip, DateTimeImmutable $since): int
    {
        $statement = $this->database->pdo()->prepare(
            'SELECT COUNT(*) FROM login_attempts
             WHERE email = ? AND ip = ? AND succeeded = 0 AND attempted_at >= ?
               AND id > (
                   SELECT COALESCE(MAX(id), 0) FROM login_attempts
                   WHERE email = ? AND ip = ? AND succeeded = 1
               )',
        );
        $sinceText = $since->format('Y-m-d H:i:s');
        $statement->execute([$email, $ip, $sinceText, $email, $ip]);

        return (int) $statement->fetchColumn();
    }

    /**
     * Failures from this address for any email since $since: catches one address trying
     * many accounts. Served by the (ip, attempted_at) index.
     */
    public function recentFailuresFrom(string $ip, DateTimeImmutable $since): int
    {
        $statement = $this->database->pdo()->prepare(
            'SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND succeeded = 0 AND attempted_at >= ?',
        );
        $statement->execute([$ip, $since->format('Y-m-d H:i:s')]);

        return (int) $statement->fetchColumn();
    }
}
