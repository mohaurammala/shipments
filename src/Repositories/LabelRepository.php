<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Domain\Booking;
use App\Domain\User;
use App\Core\Database;
use DateTimeImmutable;
use DateTimeZone;

/**
 * A history of label fetches, not a cache: label links expire, so each "Open label" asks
 * Shiplogic for a fresh one.
 */
final readonly class LabelRepository
{
    public function __construct(private Database $database)
    {
    }

    public function record(Booking $booking, User $requestedBy, bool $succeeded, ?string $url, ?string $errorCode): void
    {
        $this->database->pdo()
            ->prepare('INSERT INTO labels (booking_id, status, pdf_url, error_code, requested_by) VALUES (?, ?, ?, ?, ?)')
            ->execute([$booking->id, $succeeded ? 'ok' : 'failed', $url, $errorCode, $requestedBy->id]);
    }

    /**
     * When the label was last opened successfully, for the booking page.
     */
    public function lastOpenedAt(Booking $booking): ?DateTimeImmutable
    {
        $statement = $this->database->pdo()->prepare("SELECT MAX(created_at) FROM labels WHERE booking_id = ? AND status = 'ok'");
        $statement->execute([$booking->id]);
        $value = $statement->fetchColumn();

        return is_string($value) ? new DateTimeImmutable($value, new DateTimeZone('UTC')) : null;
    }
}
