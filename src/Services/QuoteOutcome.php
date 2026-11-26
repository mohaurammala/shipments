<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Enums\QuoteResult;

final readonly class QuoteOutcome
{
    /**
     * @param string|null $message Safe to show the user.
     */
    public function __construct(
        public QuoteResult $result,
        public ?string $message = null,
    ) {
    }
}
