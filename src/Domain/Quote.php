<?php

declare(strict_types=1);

namespace App\Domain;

use DateTimeImmutable;

/**
 * One answer from POST /rates for a booking, kept as history: quotes are never overwritten.
 * inputHash records exactly which details were quoted.
 */
final readonly class Quote
{
    public const string OK = 'ok';
    public const string NO_RATES = 'no_rates';
    public const string ERROR = 'error';

    /**
     * @param list<Rate> $rates Stored rates, each with its id.
     */
    public function __construct(
        public int $id,
        public int $bookingId,
        public string $inputHash,
        public string $outcome,
        public DateTimeImmutable $requestedAt,
        public array $rates,
    ) {
    }

    public function rate(int $rateId): ?Rate
    {
        foreach ($this->rates as $rate) {
            if ($rate->id === $rateId) {
                return $rate;
            }
        }

        return null;
    }
}
