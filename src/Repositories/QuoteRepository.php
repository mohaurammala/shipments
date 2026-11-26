<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Domain\Booking;
use App\Domain\Money;
use App\Domain\Quote;
use App\Domain\Rate;
use DateTimeImmutable;
use DateTimeZone;

final readonly class QuoteRepository
{
    public function __construct(private Database $database)
    {
    }

    /**
     * Stores a quote and its rates together: either all of it is saved or none of it.
     *
     * @param list<Rate> $rates
     */
    public function save(Booking $booking, string $inputHash, string $outcome, array $rates, DateTimeImmutable $now): Quote
    {
        return $this->database->transaction(function () use ($booking, $inputHash, $outcome, $rates, $now): Quote {
            $pdo = $this->database->pdo();
            $pdo->prepare('INSERT INTO quotes (booking_id, input_hash, outcome, requested_at) VALUES (?, ?, ?, ?)')
                ->execute([$booking->id, $inputHash, $outcome, self::dateTime($now)]);
            $quoteId = (int) $pdo->lastInsertId();

            $insert = $pdo->prepare(
                'INSERT INTO quote_rates
                    (quote_id, service_level_code, service_level_id, service_name, service_description, total_amount,
                     amount_excluding_vat, vat_treatment, vat_percentage, estimated_delivery_from, estimated_delivery_to,
                     collection_cut_off)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            );
            $stored = [];

            foreach ($rates as $rate) {
                $insert->execute([
                    $quoteId,
                    $rate->serviceLevelCode,
                    $rate->serviceLevelId,
                    mb_substr($rate->serviceName, 0, 255),
                    mb_substr($rate->description, 0, 500),
                    $rate->total->toDecimal(),
                    $rate->totalExcludingVat?->toDecimal(),
                    mb_substr($rate->vatType, 0, 16),
                    $rate->vatPercentage,
                    self::dateTime($rate->deliveryFrom),
                    self::dateTime($rate->deliveryTo),
                    self::dateTime($rate->collectionCutOff),
                ]);
                $stored[] = $rate->withId((int) $pdo->lastInsertId());
            }

            return new Quote($quoteId, $booking->id, $inputHash, $outcome, $now, $stored);
        });
    }

    /**
     * The booking's most recent quote, whatever its outcome.
     */
    public function latestFor(Booking $booking): ?Quote
    {
        $statement = $this->database->pdo()->prepare(
            'SELECT id, booking_id, input_hash, outcome, requested_at FROM quotes WHERE booking_id = ? ORDER BY id DESC LIMIT 1',
        );
        $statement->execute([$booking->id]);
        $row = $statement->fetch();

        if ($row === false) {
            return null;
        }

        $rates = $this->database->pdo()->prepare(
            'SELECT id, service_level_code, service_level_id, service_name, service_description, total_amount,
                    amount_excluding_vat, vat_treatment, vat_percentage, estimated_delivery_from, estimated_delivery_to,
                    collection_cut_off
             FROM quote_rates WHERE quote_id = ? ORDER BY total_amount, id',
        );
        $rates->execute([$row['id']]);

        return new Quote(
            id: (int) $row['id'],
            bookingId: (int) $row['booking_id'],
            inputHash: (string) $row['input_hash'],
            outcome: (string) $row['outcome'],
            requestedAt: self::parse((string) $row['requested_at']),
            rates: array_map(self::toRate(...), $rates->fetchAll()),
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function toRate(array $row): Rate
    {
        return new Rate(
            id: (int) $row['id'],
            serviceLevelCode: (string) $row['service_level_code'],
            serviceLevelId: $row['service_level_id'] === null ? null : (int) $row['service_level_id'],
            serviceName: (string) $row['service_name'],
            description: (string) $row['service_description'],
            total: Money::fromDecimal((string) $row['total_amount']),
            totalExcludingVat: $row['amount_excluding_vat'] === null ? null : Money::fromDecimal((string) $row['amount_excluding_vat']),
            vatType: (string) $row['vat_treatment'],
            vatPercentage: $row['vat_percentage'] === null ? null : (float) $row['vat_percentage'],
            deliveryFrom: $row['estimated_delivery_from'] === null ? null : self::parse((string) $row['estimated_delivery_from']),
            deliveryTo: $row['estimated_delivery_to'] === null ? null : self::parse((string) $row['estimated_delivery_to']),
            collectionCutOff: $row['collection_cut_off'] === null ? null : self::parse((string) $row['collection_cut_off']),
        );
    }

    private static function dateTime(?DateTimeImmutable $at): ?string
    {
        return $at?->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    private static function parse(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value, new DateTimeZone('UTC'));
    }
}
