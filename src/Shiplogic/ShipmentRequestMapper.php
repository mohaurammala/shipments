<?php

declare(strict_types=1);

namespace App\Shiplogic;

use App\Domain\Parcel;
use App\Domain\ShipmentInput;

/**
 * ShipmentInput + the chosen service level → POST /shipments body.
 *
 * In sandbox mode every shipment is marked "SANDBOX TEST ONLY - DO NOT COLLECT / DELIVER"
 */
final readonly class ShipmentRequestMapper
{
    public const string COLLECT_MARKER = 'SANDBOX TEST ONLY - DO NOT COLLECT';
    public const string DELIVER_MARKER = 'SANDBOX TEST ONLY - DO NOT DELIVER';

    public function __construct(private bool $isTestEnvironment)
    {
    }

    /**
     * @param string $customerReference The booking's public id, so an uncertain outcome
     *                                  can be matched to a shipment in the portal.
     * @return array<string, mixed>
     */
    public function map(ShipmentInput $shipment, string $serviceLevelCode, string $customerReference): array
    {
        return [
            'collection_address' => Payload::address($shipment->collectionAddress),
            'collection_contact' => Payload::contact($shipment->collectionContact),
            'delivery_address' => Payload::address($shipment->deliveryAddress),
            'delivery_contact' => Payload::contact($shipment->deliveryContact),
            'parcels' => array_map(
                static fn (Parcel $parcel): array => ['parcel_description' => $parcel->description, ...Payload::parcelSize($parcel)],
                $shipment->parcels,
            ),
            'declared_value' => Payload::amount($shipment->declaredValue),
            'special_instructions_collection' => $this->instructions($shipment->collectionInstructions, self::COLLECT_MARKER),
            'special_instructions_delivery' => $this->instructions($shipment->deliveryInstructions, self::DELIVER_MARKER),
            'service_level_code' => $serviceLevelCode,
            'customer_reference' => $customerReference,
            'customer_reference_name' => 'Booking ID',
            'mute_notifications' => $this->isTestEnvironment,
        ];
    }

    private function instructions(?string $text, string $marker): string
    {
        $text ??= '';

        if (!$this->isTestEnvironment || str_contains($text, $marker)) {
            return $text;
        }

        return $text === '' ? $marker : "{$marker}. {$text}";
    }
}
