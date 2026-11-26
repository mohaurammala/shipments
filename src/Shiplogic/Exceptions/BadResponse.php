<?php

declare(strict_types=1);

namespace App\Shiplogic\Exceptions;

/**
 * A success status, but a body we could not understand (not JSON, or a field missing).
 * The request was processed, so for a shipment the outcome is unknown.
 */
final class BadResponse extends ShiplogicException
{
    public function __construct(string $problem)
    {
        parent::__construct("Unexpected response from Shiplogic: {$problem}");
    }

    public function isRetryable(): bool
    {
        return false;
    }

    public function mayHaveReachedServer(): bool
    {
        return true;
    }
}
