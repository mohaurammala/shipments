<?php

declare(strict_types=1);

namespace App\Shiplogic;

use App\Domain\Money;
use App\Domain\Rate;
use App\Shiplogic\Exceptions\BadResponse;
use DateTimeImmutable;
use DateTimeZone;
use Exception;

/**
 * The rates in a POST /rates answer (see docs/API-NOTES.md), checked field by field: a rate
 * we cannot price or book is a BadResponse, never a guess.
 */
final readonly class RatesResponse
{
    /**
     * @param list<Rate> $rates Possibly empty: no service for this shipment.
     */
    public function __construct(public array $rates)
    {
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $rates = $data['rates'] ?? null;

        if (!is_array($rates)) {
            throw new BadResponse('no rates list.');
        }

        return new self(array_values(array_map(self::rate(...), $rates)));
    }

    private static function rate(mixed $rate): Rate
    {
        if (!is_array($rate) || !is_array($rate['service_level'] ?? null)) {
            throw new BadResponse('a rate has no service level.');
        }

        $level = $rate['service_level'];
        $base = is_array($rate['base_rate'] ?? null) ? $rate['base_rate'] : [];
        $code = $level['code'] ?? null;

        if (!is_string($code) || $code === '') {
            throw new BadResponse('a rate has no service level code.');
        }

        if (!is_int($rate['rate'] ?? null) && !is_float($rate['rate'] ?? null)) {
            throw new BadResponse("rate {$code} has no price.");
        }

        return new Rate(
            id: null,
            serviceLevelCode: $code,
            serviceLevelId: is_int($level['id'] ?? null) ? $level['id'] : null,
            serviceName: is_string($level['name'] ?? null) && $level['name'] !== '' ? $level['name'] : $code,
            description: is_string($level['description'] ?? null) ? trim($level['description']) : '',
            total: self::money($rate['rate']),
            totalExcludingVat: is_int($rate['rate_excluding_vat'] ?? null) || is_float($rate['rate_excluding_vat'] ?? null)
                ? self::money($rate['rate_excluding_vat'])
                : null,
            vatType: is_string($base['vat_type'] ?? null) ? $base['vat_type'] : (is_string($level['vat_type'] ?? null) ? $level['vat_type'] : 'unknown'),
            vatPercentage: is_int($base['vat_percentage'] ?? null) || is_float($base['vat_percentage'] ?? null)
                ? (float) $base['vat_percentage']
                : null,
            deliveryFrom: self::date($level['delivery_date_from'] ?? null),
            deliveryTo: self::date($level['delivery_date_to'] ?? null),
            collectionCutOff: self::date($level['collection_cut_off_time'] ?? null),
        );
    }

    /**
     * Amounts arrive as floats with many decimals (101.08695652173914). They are rounded to
     * whole cents once, here, and stay integers from then on.
     */
    private static function money(int|float $amount): Money
    {
        if ($amount < 0) {
            throw new BadResponse('a negative price.');
        }

        return Money::fromCents((int) round($amount * 100));
    }

    /**
     * Shiplogic sends times with an offset (+02:00); we keep them in UTC like everything else.
     */
    private static function date(mixed $value): ?DateTimeImmutable
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        try {
            return (new DateTimeImmutable($value))->setTimezone(new DateTimeZone('UTC'));
        } catch (Exception) {
            return null;
        }
    }
}
