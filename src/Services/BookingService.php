<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Interfaces\Clock;
use App\Core\Logger;
use App\Domain\Booking;
use App\Domain\Enums\BookingStatus;
use App\Domain\User;
use App\Repositories\BookingRepository;
use App\Services\Enums\BookResult;
use App\Shiplogic\CreatedShipment;
use App\Shiplogic\Exceptions\HttpError;
use App\Shiplogic\Exceptions\ShiplogicException;
use App\Shiplogic\ShiplogicClient;
use App\Shiplogic\ShiplogicErrors;
use App\Shiplogic\ShipmentRequestMapper;
use PDOException;
use Throwable;

/**
 * Creating the shipment, at most once per booking.
 *
 * Duplicate protection, in layers:
 *   1. The browser locks the Book button on the first click (app.js) and every form ends in
 *      a redirect, so neither a double click nor a refresh sends it twice. Helpful, but a
 *      browser can be bypassed, so:
 *   2. claim() moves SELECTED → CREATING in one conditional UPDATE. Whatever arrives at once
 *      (two tabs, a replayed request), the database lets exactly one request through; the
 *      rest never reach Shiplogic.
 *   3. POST /shipments is never retried automatically. When its outcome is uncertain (a
 *      timeout, a 5xx, an unreadable success), the booking becomes OUTCOME_UNKNOWN, which
 *      cannot be booked again until someone confirms with the courier.
 */
final readonly class BookingService
{
    private const string UNKNOWN_MESSAGE = 'We could not confirm whether the courier accepted this booking. '
        . 'Do not book it again: an administrator will check with the courier and update it.';

    public function __construct(
        private BookingRepository $bookings,
        private QuoteService $quotes,
        private ShiplogicClient $shiplogic,
        private ShipmentRequestMapper $mapper,
        private Clock $clock,
        private Logger $logger,
        private int $stuckAfterSeconds,
    ) {
    }

    public function book(Booking $booking): BookingOutcome
    {
        if (!in_array($booking->status, [BookingStatus::Selected, BookingStatus::Failed], true)) {
            return new BookingOutcome(BookResult::NotReady, match ($booking->status) {
                BookingStatus::Creating => 'This booking is already being processed.',
                BookingStatus::Created => 'This booking has already been made.',
                BookingStatus::OutcomeUnknown => self::UNKNOWN_MESSAGE,
                default => 'Choose a service before booking.',
            });
        }

        $rate = $this->quotes->bookableRate($booking);

        if ($rate === null) {
            return new BookingOutcome(BookResult::QuoteExpired, 'The quote has expired or no longer matches the details. Get a new quote, then book.');
        }

        if (!$this->bookings->claim($booking, $this->clock->now())) {
            return new BookingOutcome(BookResult::AlreadyInProgress, 'This booking is already being processed.');
        }

        $this->logger->info('Booking claimed', ['booking_id' => $booking->id, 'service_level_code' => $rate->serviceLevelCode]);

        try {
            $created = $this->shiplogic->createShipment(
                $this->mapper->map($booking->input, $rate->serviceLevelCode, $booking->publicId),
                $booking->id,
            );
        } catch (ShiplogicException $e) {
            return $this->afterFailedRequest($booking, $e);
        }

        return $this->recordCreated($booking, $created);
    }

    /**
     * Cancels a booked shipment with the courier. Unlike booking, this needs no claim: a
     * second cancellation (a double click, a retry after a timeout) cannot create anything,
     * so the booking stays CREATED until the courier confirms, and the user can try again.
     */
    public function cancel(Booking $booking): BookingOutcome
    {
        if ($booking->status !== BookingStatus::Created) {
            return new BookingOutcome(BookResult::NotReady, $booking->status === BookingStatus::Cancelled
                ? 'This shipment has already been cancelled.'
                : 'Only a booked shipment can be cancelled.');
        }

        if ($booking->trackingReference === null) {
            // Shiplogic finds the shipment to cancel by its tracking reference.
            return new BookingOutcome(BookResult::NotReady, 'This shipment has no tracking reference, so it cannot be cancelled here. Cancel it in the Shiplogic portal.');
        }

        try {
            $this->shiplogic->cancelShipment($booking->trackingReference, $booking->id);
        } catch (ShiplogicException $e) {
            $context = ['booking_id' => $booking->id, 'exception' => $e::class, 'status' => $e instanceof HttpError ? $e->status : null];

            if ($e->mayHaveReachedServer()) {
                $this->logger->error('Cancellation outcome unknown', $context);

                return new BookingOutcome(BookResult::OutcomeUnknown, 'We could not confirm the cancellation with the courier. The shipment is still shown as booked; try cancelling again in a moment.');
            }

            $this->logger->warning('Cancellation refused', $context);

            return new BookingOutcome(BookResult::Failed, ShiplogicErrors::forUser($e, 'The courier did not cancel this shipment. It may already have been collected.'));
        }

        // False only if another request recorded the same cancellation first: either way,
        // the shipment is cancelled.
        if ($this->bookings->markCancelled($booking, $this->clock->now())) {
            $this->logger->info('Shipment cancelled', ['booking_id' => $booking->id, 'shipment_id' => $booking->shipmentId]);
        }

        return new BookingOutcome(BookResult::Cancelled, 'Cancelled. The courier will not collect this shipment.');
    }

    /**
     * A request that claimed a booking and then died (a crash, a restart) leaves it in
     * CREATING with nobody to finish it. After the longest a request can take, it is
     * treated as unknown: someone must check with the courier before it is booked again.
     */
    public function settleIfStuck(Booking $booking): bool
    {
        $stuckSince = $this->clock->now()->modify("-{$this->stuckAfterSeconds} seconds");

        if ($booking->status !== BookingStatus::Creating || $booking->statusChangedAt > $stuckSince) {
            return false;
        }

        $moved = $this->bookings->markUnknown($booking, $this->clock->now(), $stuckSince);

        if ($moved) {
            $this->logger->warning('Booking stuck in CREATING marked as unknown', ['booking_id' => $booking->id]);
        }

        return $moved;
    }

    /**
     * An admin's answer after checking an uncertain booking in the Shiplogic portal (found
     * by its customer reference, the booking id): booked with this shipment id, or not booked.
     */
    public function resolve(Booking $booking, User $admin, ?string $shipmentId, ?string $trackingReference): BookingOutcome
    {
        if ($booking->status !== BookingStatus::OutcomeUnknown) {
            return new BookingOutcome(BookResult::NotReady, 'Only bookings with an unknown outcome can be resolved.');
        }

        $now = $this->clock->now();

        if ($shipmentId === null) {
            $this->bookings->markFailed($booking, 'Checked with the courier: no shipment was created. You can book again.', $now, BookingStatus::OutcomeUnknown);
            $this->logger->info('Unknown booking resolved: not booked', ['booking_id' => $booking->id, 'by' => $admin->id]);

            return new BookingOutcome(BookResult::Failed, 'Marked as not booked. The owner can book it again.');
        }

        try {
            $this->bookings->markCreated($booking, $shipmentId, $trackingReference, $now, BookingStatus::OutcomeUnknown);
        } catch (PDOException) {
            // UNIQUE(shiplogic_shipment_id): one shipment can never belong to two bookings.
            return new BookingOutcome(BookResult::NotReady, 'That shipment id already belongs to another booking.');
        }

        $this->logger->info('Unknown booking resolved: booked', ['booking_id' => $booking->id, 'shipment_id' => $shipmentId, 'by' => $admin->id]);

        return new BookingOutcome(BookResult::Booked, 'Marked as booked.');
    }

    private function afterFailedRequest(Booking $booking, ShiplogicException $e): BookingOutcome
    {
        $context = [
            'booking_id' => $booking->id,
            'exception' => $e::class,
            'status' => $e instanceof HttpError ? $e->status : null,
        ];

        if (!$e->mayHaveReachedServer()) {
            $reason = ShiplogicErrors::forUser($e, 'The courier did not accept the booking. Check the details and try again.');
            $this->bookings->markFailed($booking, $reason, $this->clock->now());
            $this->logger->warning('Booking failed', $context);

            return new BookingOutcome(BookResult::Failed, $reason);
        }

        $this->bookings->markUnknown($booking, $this->clock->now());
        $this->logger->error('Booking outcome unknown', $context);

        return new BookingOutcome(BookResult::OutcomeUnknown, self::UNKNOWN_MESSAGE);
    }

    private function recordCreated(Booking $booking, CreatedShipment $created): BookingOutcome
    {
        try {
            $saved = $this->bookings->markCreated($booking, $created->id, $created->trackingReference, $this->clock->now());
        } catch (Throwable) {
            $saved = false;
        }

        if (!$saved) {
            // The shipment exists at Shiplogic but our record did not change. Its id is
            // logged so an admin can resolve the booking with it.
            $this->logger->error('Shipment created but not recorded', ['booking_id' => $booking->id, 'shipment_id' => $created->id]);

            try {
                $this->bookings->markUnknown($booking, $this->clock->now());
            } catch (Throwable) {
            }

            return new BookingOutcome(BookResult::OutcomeUnknown, self::UNKNOWN_MESSAGE);
        }

        $this->logger->info('Shipment created', ['booking_id' => $booking->id, 'shipment_id' => $created->id]);

        return new BookingOutcome(BookResult::Booked, 'Booked. Your label is ready to print.');
    }
}
