<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Shiplogic answers shaped like the documented examples (docs/API-NOTES.md).
 */
final class ShiplogicFixture
{
    /**
     * Two of the five rates from the documented sandbox response.
     *
     * @return array<string, mixed>
     */
    public static function rates(): array
    {
        return [
            'message' => 'Success',
            'rates' => [
                self::rate('LOF', 212573, 'Local Overnight Flyer', '', 116.25, 101.08695652173914, '2026-11-26T16:00:00+02:00', '2026-11-27T15:00:00+02:00'),
                self::rate('ECO', 212570, 'Economy', 'Delivered within 2-3 days', 120, 104.34782608695653, '2026-11-30T08:00:00+02:00', '2026-12-01T17:00:00+02:00'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function rate(string $code, int $id, string $name, string $description, int|float $total, int|float $excludingVat, string $from, string $to): array
    {
        return [
            'rate' => $total,
            'rate_excluding_vat' => $excludingVat,
            'base_rate' => ['vat_type' => 'standard', 'vat_percentage' => 15],
            'service_level' => [
                'id' => $id,
                'code' => $code,
                'name' => $name,
                'description' => $description,
                'delivery_date_from' => $from,
                'delivery_date_to' => $to,
                'collection_cut_off_time' => '2026-11-26T14:00:00+02:00',
            ],
            'extras' => [['insurance_charge' => 25]],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function createdShipment(int $id = 14135): array
    {
        return ['id' => $id, 'short_tracking_reference' => 'G9GXYZ', 'status' => 'submitted'];
    }

    /**
     * The fields we read from a POST /shipments/cancel answer (the real one is the whole
     * shipment, addresses, parcels and tracking events included).
     *
     * @return array<string, mixed>
     */
    public static function cancelledShipment(int $id = 14135): array
    {
        return [
            'id' => $id,
            'short_tracking_reference' => 'G9GXYZ',
            'status' => 'cancelled',
            'parcels' => [['tracking_reference' => 'G9GXYZ/1', 'status' => 'cancelled']],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function label(): array
    {
        return [
            'url' => 'https://labels.prod-shared.shiplogic.com/sandbox_Stickers_1_ABC.pdf?Expires=1&Signature=test',
            'filename' => 'sandbox_Stickers_1_ABC.pdf',
            'expiry_duration' => '7 days',
        ];
    }
}
