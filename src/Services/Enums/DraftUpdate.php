<?php

declare(strict_types=1);

namespace App\Services\Enums;

enum DraftUpdate
{
    /**
     * The details changed: the booking is a draft again and needs a new quote.
     */
    case Updated;

    /**
     * Nothing that matters changed (same normalised input), so any quote still stands.
     */
    case Unchanged;

    /**
     * The booking is being booked, is booked, or its outcome is unknown: no edits.
     */
    case Locked;
}
