<?php

declare(strict_types=1);

namespace App\Shiplogic;

use App\Domain\Parcel;
use App\Domain\ShipmentInput;

/**
 * ShipmentInput → POST /rates body. Contacts are not part of a rates request.
 */
final readonly class RatesRequestMapper
{
    /**
     * @return array<string, mixed>
     */
    public function map(ShipmentInput $shipment): array
    {
        return [
            'collection_address' => Payload::address($shipment->collectionAddress),
            'delivery_address' => Payload::address($shipment->deliveryAddress),
            'parcels' => array_map(static fn (Parcel $parcel): array => Payload::parcelSize($parcel), $shipment->parcels),
            'declared_value' => Payload::amount($shipment->declaredValue),
        ];
    }
}
