<?php

declare(strict_types=1);

namespace App\Domain;

use App\Domain\Enums\BookingStatus;
use DateTimeImmutable;

/**
 * One booking as stored: the shipment the user entered, and where it is in the workflow.
 * publicId (a random UUID) is what URLs show; id never leaves the server.
 */
final readonly class Booking
{
    public function __construct(
        public int $id,
        public string $publicId,
        public int $userId,
        public BookingStatus $status,
        public ShipmentInput $input,
        public string $inputHash,
        public ?int $selectedRateId,
        public ?string $shipmentId,
        public ?string $trackingReference,
        public DateTimeImmutable $statusChangedAt,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
        public ?string $failureReason = null,
    ) {
    }
}
