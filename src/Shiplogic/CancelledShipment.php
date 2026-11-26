<?php

declare(strict_types=1);

namespace App\Shiplogic;

use App\Shiplogic\Exceptions\BadResponse;

/**
 * What we keep from a POST /shipments/cancel answer. The response is the whole shipment;
 * only its status matters here. A success status whose body does not say "cancelled" is not
 * trusted: the booking stays booked and the user can try again.
 */
final readonly class CancelledShipment
{
    public const string STATUS = 'cancelled';

    public function __construct(
        public string $id,
        public string $status,
    ) {
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $id = $data['id'] ?? null;
        $status = $data['status'] ?? null;

        if ((!is_int($id) && !is_string($id)) || (string) $id === '') {
            throw new BadResponse('the cancelled shipment has no id.');
        }

        if (!is_string($status) || strtolower($status) !== self::STATUS) {
            throw new BadResponse('the shipment is not marked as cancelled.');
        }

        return new self((string) $id, strtolower($status));
    }
}
