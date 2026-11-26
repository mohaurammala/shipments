<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * A row in the bookings list: the booking plus its owner, which admins see.
 */
final readonly class BookingListItem
{
    public function __construct(
        public Booking $booking,
        public string $ownerEmail,
    ) {
    }
}
