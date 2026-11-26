<?php

declare(strict_types=1);

namespace App\Shiplogic\Exceptions;

/**
 * No complete response arrived: the connection failed, or the request or response timed out.
 */
final class NetworkFailure extends ShiplogicException
{
    /**
     * @param bool $requestSent False only when no connection was ever made, so Shiplogic
     *                          cannot have seen the request.
     */
    public function __construct(
        public readonly bool $requestSent,
        public readonly bool $timedOut,
    ) {
        parent::__construct(match (true) {
            !$requestSent => 'Could not connect to Shiplogic.',
            $timedOut => 'Shiplogic did not answer in time.',
            default => 'The connection to Shiplogic failed during the request.',
        });
    }

    public function isRetryable(): bool
    {
        return true;
    }

    public function mayHaveReachedServer(): bool
    {
        return $this->requestSent;
    }
}
