<?php

declare(strict_types=1);

namespace App\Shiplogic;

use App\Shiplogic\Exceptions\BadResponse;

/**
 * What we keep from a POST /shipments answer. The id is the one the label endpoint needs
 * (not provider_shipment_id). Which tracking field the response carries is to be confirmed
 * in the sandbox, so the first one present is used.
 */
final readonly class CreatedShipment
{
    private const array TRACKING_FIELDS = ['short_tracking_reference', 'custom_tracking_reference', 'tracking_reference'];

    public function __construct(
        public string $id,
        public ?string $trackingReference,
    ) {
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $id = $data['id'] ?? null;

        if ((!is_int($id) && !is_string($id)) || (string) $id === '' || (string) $id === '0') {
            // A success status without an id: the shipment probably exists, but we cannot
            // name it. The booking's outcome is unknown, never "failed".
            throw new BadResponse('the created shipment has no id.');
        }

        $tracking = null;

        foreach (self::TRACKING_FIELDS as $field) {
            if (is_string($data[$field] ?? null) && $data[$field] !== '') {
                $tracking = $data[$field];
                break;
            }
        }

        return new self((string) $id, $tracking);
    }
}
