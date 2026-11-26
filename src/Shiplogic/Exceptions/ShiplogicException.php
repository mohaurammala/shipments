<?php

declare(strict_types=1);

namespace App\Shiplogic\Exceptions;

use RuntimeException;

/**
 * A call to Shiplogic did not produce a usable answer. Messages are generic on purpose:
 * no URLs, no request or response bodies, nothing personal. Subclasses say what kind of
 * failure it was, which decides whether a booking is FAILED or OUTCOME_UNKNOWN.
 */
abstract class ShiplogicException extends RuntimeException
{
    /**
     * Whether repeating the same request is safe and might work. Never consulted for
     * POST /shipments, which is not retried at all.
     */
    abstract public function isRetryable(): bool;

    /**
     * Whether Shiplogic may have received and acted on the request. When true for a
     * shipment, the shipment may exist even though we have no answer.
     */
    abstract public function mayHaveReachedServer(): bool;
}
