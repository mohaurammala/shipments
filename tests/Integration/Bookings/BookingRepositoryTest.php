<?php

declare(strict_types=1);

namespace Tests\Integration\Bookings;

use App\Domain\Enums\BookingStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

final class BookingRepositoryTest extends BookingTestCase
{
    #[Test]
    public function aSavedBookingIsADraftThatReadsBackExactly(): void
    {
        $owner = $this->user('owner@example.test');
        $shipment = $this->shipment();

        $booking = $this->bookings->create($owner, $shipment, $this->clock->now());

        self::assertSame(BookingStatus::Draft, $booking->status);
        self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $booking->publicId);
        self::assertEquals($shipment, $booking->input);
        self::assertSame($shipment->hash(), $booking->inputHash);
        self::assertSame('2026-11-25 10:00:00', $booking->createdAt->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function anotherOperatorCannotFindIt(): void
    {
        $booking = $this->bookings->create($this->user('owner@example.test'), $this->shipment(), $this->clock->now());

        self::assertNull($this->bookings->findForUser($booking->publicId, $this->user('someone-else@example.test')));
    }

    #[Test]
    public function anAdminCanFindAnyBooking(): void
    {
        $booking = $this->bookings->create($this->user('owner@example.test'), $this->shipment(), $this->clock->now());

        self::assertSame($booking->id, $this->bookings->findForUser($booking->publicId, $this->user('admin@example.test', 'admin'))?->id);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notBookingIds(): iterable
    {
        yield 'path traversal' => ['../../etc/passwd'];
        yield 'number' => ['1'];
        yield 'almost a UUID' => ['7f3a9c00-0000-4000-8000-00000000000z'];
        yield 'empty' => [''];
    }

    #[Test]
    #[DataProvider('notBookingIds')]
    public function somethingThatIsNotABookingIdFindsNothing(string $id): void
    {
        self::assertNull($this->bookings->findForUser($id, $this->user('admin@example.test', 'admin')));
    }

    #[Test]
    public function theIdIsNotCaseSensitive(): void
    {
        $owner = $this->user('owner@example.test');
        $booking = $this->bookings->create($owner, $this->shipment(), $this->clock->now());

        self::assertSame($booking->id, $this->bookings->findForUser(strtoupper($booking->publicId), $owner)?->id);
    }

    #[Test]
    public function theListShowsYourOwnBookingsNewestFirst(): void
    {
        $owner = $this->user('owner@example.test');
        $first = $this->bookings->create($owner, $this->shipment(), $this->clock->now());
        $this->clock->advance('+1 hour');
        $second = $this->bookings->create($owner, $this->shipment(), $this->clock->now());
        $this->bookings->create($this->user('someone-else@example.test'), $this->shipment(), $this->clock->now());

        $ids = array_map(static fn ($item): int => $item->booking->id, $this->bookings->listForUser($owner));

        self::assertSame([$second->id, $first->id], $ids);
    }

    #[Test]
    public function anAdminListsEveryonesBookingsWithTheirOwners(): void
    {
        $this->bookings->create($this->user('owner@example.test'), $this->shipment(), $this->clock->now());
        $this->bookings->create($this->user('someone-else@example.test'), $this->shipment(), $this->clock->now());

        $owners = array_map(
            static fn ($item): string => $item->ownerEmail,
            $this->bookings->listForUser($this->user('admin@example.test', 'admin')),
        );
        sort($owners);

        self::assertSame(['owner@example.test', 'someone-else@example.test'], $owners);
    }

    #[Test]
    public function newDetailsSendTheBookingBackToDraftAndClearTheRate(): void
    {
        $owner = $this->user('owner@example.test');
        $booking = $this->moveTo($this->bookings->create($owner, $this->shipment(), $this->clock->now()), BookingStatus::Selected, $owner);
        self::assertNotNull($booking->selectedRateId);
        $heavier = $this->shipment(['parcels.0.weight_kg' => '9']);

        self::assertTrue($this->bookings->replaceInput($booking, $heavier, $this->clock->now()));

        $saved = $this->bookings->findForUser($booking->publicId, $owner);
        self::assertSame(BookingStatus::Draft, $saved?->status);
        self::assertNull($saved->selectedRateId);
        self::assertSame($heavier->hash(), $saved->inputHash);
    }

    #[Test]
    public function newDetailsAreRefusedOnceABookingRequestHasStarted(): void
    {
        $owner = $this->user('owner@example.test');
        $booking = $this->moveTo($this->bookings->create($owner, $this->shipment(), $this->clock->now()), BookingStatus::Creating, $owner);

        self::assertFalse($this->bookings->replaceInput($booking, $this->shipment(['parcels.0.weight_kg' => '9']), $this->clock->now()));
        self::assertSame(BookingStatus::Creating, $this->bookings->findForUser($booking->publicId, $owner)?->status);
    }

    #[Test]
    public function savingADraftWithNothingChangedStillCountsAsSaved(): void
    {
        $owner = $this->user('owner@example.test');
        $booking = $this->bookings->create($owner, $this->shipment(), $this->clock->now());

        // Same values, same second: MariaDB changes nothing, but the row was matched.
        self::assertTrue($this->bookings->replaceInput($booking, $this->shipment(), $this->clock->now()));
    }
}
