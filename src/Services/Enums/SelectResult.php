<?php

declare(strict_types=1);

namespace App\Services\Enums;

enum SelectResult
{
    case Selected;

    /**
     * The quote is older than QUOTE_TTL_MINUTES: prices must be fetched again.
     */
    case Expired;

    /**
     * The rate is not in the booking's current quote (an old page, or a tampered form).
     */
    case NotOffered;

    case Locked;
}
