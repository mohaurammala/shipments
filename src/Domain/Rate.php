<?php

declare(strict_types=1);

namespace App\Domain;

use DateTimeImmutable;

/**
 * One service level the courier offered in a quote, with its price.
 */
final readonly class Rate
{
    /**
     * @param int|null $id Our quote_rates id; null until the quote is stored.
     * @param Money $total What the user pays, including VAT and any insurance.
     */
    public function __construct(
        public ?int $id,
        public string $serviceLevelCode,
        public ?int $serviceLevelId,
        public string $serviceName,
        public string $description,
        public Money $total,
        public ?Money $totalExcludingVat,
        public string $vatType,
        public ?float $vatPercentage,
        public ?DateTimeImmutable $deliveryFrom,
        public ?DateTimeImmutable $deliveryTo,
        public ?DateTimeImmutable $collectionCutOff,
    ) {
    }

    public function withId(int $id): self
    {
        return clone($this, ['id' => $id]);
    }

    /**
     * The VAT in the total, when the courier said what the total excludes.
     */
    public function vat(): ?Money
    {
        return $this->totalExcludingVat === null
            ? null
            : Money::fromCents(max(0, $this->total->cents - $this->totalExcludingVat->cents));
    }
}
