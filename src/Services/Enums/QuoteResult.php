<?php

declare(strict_types=1);

namespace App\Services\Enums;

enum QuoteResult
{
    case Quoted;

    /**
     * Shiplogic offered no service for these details. The brief: do not invent alternatives.
     */
    case NoRates;

    /**
     * The call failed; the message says why, in plain words.
     */
    case Unavailable;

    /**
     * The details were edited while the quote was being fetched: that quote no longer applies.
     */
    case Stale;

    /**
     * Booking has started or finished: quoting again would change nothing.
     */
    case Locked;
}
