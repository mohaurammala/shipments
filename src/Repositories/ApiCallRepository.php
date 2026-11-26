<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/**
 * One row per call to Shiplogic: what was called, how it went, and the bodies that went out
 * and came back (prepared by ShiplogicClient, which removes URL signatures first).
 */
final readonly class ApiCallRepository
{
    public const array OUTCOMES = ['ok', 'http_error', 'timeout', 'network', 'bad_response'];

    public function __construct(private Database $database)
    {
    }

    public function record(
        ?int $bookingId,
        string $method,
        string $endpoint,
        ?int $httpStatus,
        int $durationMs,
        string $outcome,
        ?string $requestBody = null,
        ?string $responseBody = null,
    ): void {
        $this->database->pdo()
            ->prepare(
                'INSERT INTO api_calls (booking_id, method, endpoint, http_status, duration_ms, outcome, request_body, response_body)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            )
            ->execute([$bookingId, $method, $endpoint, $httpStatus, $durationMs, $outcome, $requestBody, $responseBody]);
    }

    public function count(int $bookingId, string $endpoint): int
    {
        $statement = $this->database->pdo()->prepare('SELECT COUNT(*) FROM api_calls WHERE booking_id = ? AND endpoint = ?');
        $statement->execute([$bookingId, $endpoint]);

        return (int) $statement->fetchColumn();
    }
}
