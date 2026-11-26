<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Interfaces\Clock;
use App\Core\Logger;
use App\Domain\Booking;
use App\Domain\ShipmentInput;
use App\Domain\User;
use App\Repositories\BookingRepository;
use App\Services\Enums\DraftUpdate;

/**
 * Saving and editing the shipment details of a booking.
 */
final readonly class BookingDraftService
{
    public function __construct(
        private BookingRepository $bookings,
        private Clock $clock,
        private Logger $logger,
    ) {
    }

    public function create(User $owner, ShipmentInput $input): Booking
    {
        $booking = $this->bookings->create($owner, $input, $this->clock->now());
        $this->logger->info('Booking draft created', ['booking_id' => $booking->id, 'user_id' => $owner->id]);

        return $booking;
    }

    /**
     * If the quote inputs change, the previous quote and selection no longer apply: a real change
     * sends the booking back to DRAFT; re-saving the same details keeps its quote.
     */
    public function update(Booking $booking, ShipmentInput $input): DraftUpdate
    {
        if (!$booking->status->isEditable()) {
            return DraftUpdate::Locked;
        }

        if ($input->hash() === $booking->inputHash) {
            return DraftUpdate::Unchanged;
        }

        // The repository re-checks the status inside the UPDATE, in case a booking request
        // started between loading this booking and saving it.
        if (!$this->bookings->replaceInput($booking, $input, $this->clock->now())) {
            return DraftUpdate::Locked;
        }

        $this->logger->info('Booking details changed', ['booking_id' => $booking->id, 'from_status' => $booking->status->value]);

        return DraftUpdate::Updated;
    }
}
