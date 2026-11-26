<?php

declare(strict_types=1);

namespace Tests\Integration\Bookings;

use App\Core\Logger;
use App\Domain\Booking;
use App\Domain\Enums\BookingStatus;
use App\Domain\User;
use App\Services\BookingDraftService;
use App\Services\Enums\DraftUpdate;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

final class BookingDraftServiceTest extends BookingTestCase
{
    private BookingDraftService $drafts;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $log = fopen('php://memory', 'w+b');
        self::assertIsResource($log);
        $this->drafts = new BookingDraftService($this->bookings, $this->clock, new Logger($log));
        $this->owner = $this->user('owner@example.test');
    }

    #[Test]
    public function savingTheSameDetailsAgainKeepsTheQuote(): void
    {
        $booking = $this->quotedBooking();

        self::assertSame(DraftUpdate::Unchanged, $this->drafts->update($booking, $this->shipment()));
        self::assertSame(BookingStatus::Quoted, $this->reload($booking, $this->owner)->status);
    }

    #[Test]
    public function differentlyFormattedButEqualDetailsKeepTheQuote(): void
    {
        $booking = $this->quotedBooking();
        $reformatted = $this->shipment(['collection.contact.mobile' => '+27 82-123-4567', 'declared_value' => '1500.00']);

        self::assertSame(DraftUpdate::Unchanged, $this->drafts->update($booking, $reformatted));
    }

    #[Test]
    public function changedDetailsSendAQuotedBookingBackToDraft(): void
    {
        $booking = $this->quotedBooking();

        self::assertSame(DraftUpdate::Updated, $this->drafts->update($booking, $this->shipment(['parcels.0.weight_kg' => '9'])));
        self::assertSame(BookingStatus::Draft, $this->reload($booking, $this->owner)->status);
    }

    /**
     * @return iterable<string, array{BookingStatus}>
     */
    public static function lockedStatuses(): iterable
    {
        yield 'booking in progress' => [BookingStatus::Creating];
        yield 'booked' => [BookingStatus::Created];
        yield 'outcome unknown' => [BookingStatus::OutcomeUnknown];
    }

    #[Test]
    #[DataProvider('lockedStatuses')]
    public function detailsCannotChangeOnceBookingHasStarted(BookingStatus $status): void
    {
        $booking = $this->moveTo($this->draft(), $status, $this->owner);

        self::assertSame(DraftUpdate::Locked, $this->drafts->update($booking, $this->shipment(['parcels.0.weight_kg' => '9'])));
        self::assertSame($status, $this->reload($booking, $this->owner)->status);
    }

    #[Test]
    public function anEditThatLosesTheRaceToABookingRequestIsRefused(): void
    {
        // This request loaded the booking while it was still SELECTED...
        $loadedEarlier = $this->moveTo($this->draft(), BookingStatus::Selected, $this->owner);
        // ...and meanwhile another request started booking it.
        $this->db->prepare('UPDATE bookings SET status = ? WHERE id = ?')->execute([BookingStatus::Creating->value, $loadedEarlier->id]);

        self::assertSame(DraftUpdate::Locked, $this->drafts->update($loadedEarlier, $this->shipment(['parcels.0.weight_kg' => '9'])));
        self::assertSame(BookingStatus::Creating, $this->reload($loadedEarlier, $this->owner)->status);
    }

    private function draft(): Booking
    {
        return $this->drafts->create($this->owner, $this->shipment());
    }

    private function quotedBooking(): Booking
    {
        return $this->moveTo($this->draft(), BookingStatus::Quoted, $this->owner);
    }

}
