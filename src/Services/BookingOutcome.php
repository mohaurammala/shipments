<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Enums\BookResult;

final readonly class BookingOutcome
{
    /**
     * @param string $message Safe to show the user.
     */
    public function __construct(
        public BookResult $result,
        public string $message,
    ) {
    }
}
