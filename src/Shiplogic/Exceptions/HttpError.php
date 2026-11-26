<?php

declare(strict_types=1);

namespace App\Shiplogic\Exceptions;

/**
 * Shiplogic answered with a 4xx or 5xx status.
 */
final class HttpError extends ShiplogicException
{
    /**
     * @param string|null $apiMessage Shiplogic's own message, used only to choose a friendly
     *                                message for the user. Never shown or logged as is.
     */
    public function __construct(
        public readonly int $status,
        public readonly ?string $apiMessage,
    ) {
        parent::__construct("Shiplogic answered with HTTP {$status}.");
    }

    /**
     * 4xx: Shiplogic understood and refused, so nothing happened.
     */
    public function isRejection(): bool
    {
        return $this->status >= 400 && $this->status < 500;
    }

    public function isRetryable(): bool
    {
        return $this->status >= 500;
    }

    /**
     * A 5xx may come after the work was done (e.g. the shipment saved, then the response
     * failed), so only a 4xx is a definite "nothing happened".
     */
    public function mayHaveReachedServer(): bool
    {
        return !$this->isRejection();
    }
}
