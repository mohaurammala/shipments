<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;
use App\Domain\Booking;
use App\Domain\Enums\BookingStatus;
use App\Domain\User;
use App\Repositories\LabelRepository;
use App\Shiplogic\Exceptions\BadResponse;
use App\Shiplogic\Exceptions\HttpError;
use App\Shiplogic\Exceptions\NetworkFailure;
use App\Shiplogic\Exceptions\ShiplogicException;
use App\Shiplogic\ShiplogicClient;
use App\Shiplogic\ShiplogicErrors;

/**
 * The printable label for a booked shipment. Only ever reads: retrying after a
 * failure asks again for the same shipment's label and can never create a shipment.
 */
final readonly class LabelService
{
    public function __construct(
        private LabelRepository $labels,
        private ShiplogicClient $shiplogic,
        private Logger $logger,
    ) {
    }

    public function fetch(Booking $booking, User $user): LabelOutcome
    {
        if ($booking->status === BookingStatus::Cancelled) {
            return new LabelOutcome(null, 'There is no label: this shipment was cancelled.');
        }

        if ($booking->status !== BookingStatus::Created || $booking->shipmentId === null) {
            return new LabelOutcome(null, 'There is no label yet: the shipment has not been booked.');
        }

        try {
            $url = $this->shiplogic->getLabelUrl($booking->shipmentId, $booking->id);
        } catch (ShiplogicException $e) {
            $this->labels->record($booking, $user, false, null, self::errorCode($e));
            $this->logger->warning('Label fetch failed', ['booking_id' => $booking->id, 'exception' => $e::class]);

            return new LabelOutcome(null, ShiplogicErrors::forUser($e, 'The courier could not provide the label yet. Try again in a moment.'));
        }

        // The URL opens customer details, so it is stored only here and never logged.
        $this->labels->record($booking, $user, true, $url, null);

        return new LabelOutcome($url, null);
    }

    private static function errorCode(ShiplogicException $e): string
    {
        return match (true) {
            $e instanceof HttpError => "http_{$e->status}",
            $e instanceof NetworkFailure => $e->timedOut ? 'timeout' : 'network',
            $e instanceof BadResponse => 'bad_response',
            default => 'error',
        };
    }
}
