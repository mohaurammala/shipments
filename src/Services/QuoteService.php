<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Interfaces\Clock;
use App\Core\Logger;
use App\Domain\Booking;
use App\Domain\Enums\BookingStatus;
use App\Domain\Quote;
use App\Domain\Rate;
use App\Repositories\BookingRepository;
use App\Repositories\QuoteRepository;
use App\Services\Enums\QuoteResult;
use App\Services\Enums\SelectResult;
use App\Shiplogic\Exceptions\HttpError;
use App\Shiplogic\Exceptions\ShiplogicException;
use App\Shiplogic\RatesRequestMapper;
use App\Shiplogic\ShiplogicClient;
use App\Shiplogic\ShiplogicErrors;
use DateTimeImmutable;

/**
 * Getting a quote, and choosing one of its services.
 *
 * A quote applies to a booking only while three things hold: it is the latest quote, it was
 * made for the booking's current details (same input_hash), and it is younger than
 * QUOTE_TTL_MINUTES. Anything else needs a fresh quote before booking.
 */
final readonly class QuoteService
{
    public function __construct(
        private BookingRepository $bookings,
        private QuoteRepository $quotes,
        private ShiplogicClient $shiplogic,
        private RatesRequestMapper $mapper,
        private Clock $clock,
        private Logger $logger,
        private int $ttlMinutes,
    ) {
    }

    public function quote(Booking $booking): QuoteOutcome
    {
        if (!$booking->status->canTransitionTo(BookingStatus::Quoted)) {
            return new QuoteOutcome(QuoteResult::Locked, 'This booking can no longer be quoted.');
        }

        try {
            $response = $this->shiplogic->getRates($this->mapper->map($booking->input), $booking->id);
        } catch (ShiplogicException $e) {
            $this->quotes->save($booking, $booking->inputHash, Quote::ERROR, [], $this->clock->now());
            $this->logger->warning('Quote failed', [
                'booking_id' => $booking->id,
                'exception' => $e::class,
                'status' => $e instanceof HttpError ? $e->status : null,
            ]);

            return new QuoteOutcome(
                QuoteResult::Unavailable,
                ShiplogicErrors::forUser($e, 'The courier could not quote for these details. Check them and try again.'),
            );
        }

        $now = $this->clock->now();

        if ($response->rates === []) {
            $this->quotes->save($booking, $booking->inputHash, Quote::NO_RATES, [], $now);
            $this->bookings->afterQuote($booking, BookingStatus::Draft, $now);

            return new QuoteOutcome(QuoteResult::NoRates, 'The courier has no service for these details. Check the addresses and parcels.');
        }

        $this->quotes->save($booking, $booking->inputHash, Quote::OK, $response->rates, $now);

        if (!$this->bookings->afterQuote($booking, BookingStatus::Quoted, $now)) {
            return new QuoteOutcome(QuoteResult::Stale, 'The details changed while the quote was being prepared. Get a new quote.');
        }

        return new QuoteOutcome(QuoteResult::Quoted);
    }

    public function select(Booking $booking, int $rateId): SelectResult
    {
        if (!in_array($booking->status, [BookingStatus::Quoted, BookingStatus::Selected], true)) {
            return SelectResult::Locked;
        }

        $quote = $this->currentQuote($booking);

        if ($quote === null || $quote->rate($rateId) === null) {
            return SelectResult::NotOffered;
        }

        if ($this->isExpired($quote)) {
            return SelectResult::Expired;
        }

        return $this->bookings->select($booking, $rateId, $this->clock->now())
            ? SelectResult::Selected
            : SelectResult::Locked;
    }

    /**
     * The latest successful quote, if it was made for the booking's current details.
     */
    public function currentQuote(Booking $booking): ?Quote
    {
        $quote = $this->quotes->latestFor($booking);

        return $quote !== null && $quote->outcome === Quote::OK && $quote->inputHash === $booking->inputHash
            ? $quote
            : null;
    }

    /**
     * The latest attempt, whatever happened, so the page can say "no services" or "failed".
     */
    public function latestQuote(Booking $booking): ?Quote
    {
        return $this->quotes->latestFor($booking);
    }

    /**
     * The rate the booking would be booked with, if its quote still applies and is fresh.
     */
    public function bookableRate(Booking $booking): ?Rate
    {
        $quote = $this->currentQuote($booking);

        if ($quote === null || $booking->selectedRateId === null || $this->isExpired($quote)) {
            return null;
        }

        return $quote->rate($booking->selectedRateId);
    }

    public function expiresAt(Quote $quote): DateTimeImmutable
    {
        return $quote->requestedAt->modify("+{$this->ttlMinutes} minutes");
    }

    public function isExpired(Quote $quote): bool
    {
        return $this->expiresAt($quote) <= $this->clock->now();
    }
}
