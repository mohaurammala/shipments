<?php

declare(strict_types=1);

namespace App\Services\Enums;

enum BookResult
{
    case Booked;

    /**
     * Shiplogic definitely did not create a shipment (a 4xx, or no connection was made).
     * Booking again is safe.
     */
    case Failed;

    /**
     * The request may have been acted on, but we have no answer. A booking request is never
     * retried automatically (an admin confirms with the courier); a cancellation can simply
     * be tried again.
     */
    case OutcomeUnknown;

    case QuoteExpired;

    /**
     * Another request claimed this booking first (a double click, a second tab).
     */
    case AlreadyInProgress;

    case NotReady;

    /**
     * The courier confirmed the shipment is cancelled.
     */
    case Cancelled;
}
