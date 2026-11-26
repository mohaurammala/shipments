<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\Enums\BookingStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BookingStatusTest extends TestCase
{
    /**
     * The booking state machine, written out independently of the enum, so a
     * mistake in either one makes the tests fail.
     */
    private const array ALLOWED = [
        'DRAFT' => ['DRAFT', 'QUOTED'],
        'QUOTED' => ['DRAFT', 'QUOTED', 'SELECTED'],
        'SELECTED' => ['DRAFT', 'QUOTED', 'SELECTED', 'CREATING'],
        'CREATING' => ['CREATED', 'FAILED', 'OUTCOME_UNKNOWN'],
        'FAILED' => ['DRAFT', 'QUOTED', 'CREATING'],
        'OUTCOME_UNKNOWN' => ['CREATED', 'FAILED'],
        'CREATED' => ['CANCELLED'],
        'CANCELLED' => [],
    ];

    /**
     * Every pair of statuses: 8 × 8 = 64 cases.
     *
     * @return iterable<string, array{BookingStatus, BookingStatus, bool}>
     */
    public static function everyPair(): iterable
    {
        foreach (BookingStatus::cases() as $from) {
            foreach (BookingStatus::cases() as $to) {
                $allowed = in_array($to->value, self::ALLOWED[$from->value], true);

                yield "{$from->value} → {$to->value}" => [$from, $to, $allowed];
            }
        }
    }

    #[Test]
    #[DataProvider('everyPair')]
    public function eachMoveIsAllowedOnlyIfTheDesignAllowsIt(BookingStatus $from, BookingStatus $to, bool $allowed): void
    {
        self::assertSame($allowed, $from->canTransitionTo($to));
    }

    #[Test]
    public function aCreatedBookingCanOnlyBeCancelledAndCancellingIsFinal(): void
    {
        self::assertFalse(BookingStatus::Created->isFinal());
        self::assertFalse(BookingStatus::Created->isEditable());
        self::assertTrue(BookingStatus::Cancelled->isFinal());
        self::assertFalse(BookingStatus::Cancelled->isEditable());
    }

    #[Test]
    public function detailsCannotChangeWhileABookingIsInFlightOrUncertain(): void
    {
        self::assertFalse(BookingStatus::Creating->isEditable());
        self::assertFalse(BookingStatus::OutcomeUnknown->isEditable());
    }

    #[Test]
    public function detailsCanChangeBeforeBookingAndAfterADefiniteFailure(): void
    {
        foreach ([BookingStatus::Draft, BookingStatus::Quoted, BookingStatus::Selected, BookingStatus::Failed] as $status) {
            self::assertTrue($status->isEditable(), $status->value);
        }
    }

    #[Test]
    public function theEnumAndTheDatabaseAgreeOnTheStatuses(): void
    {
        // The constraint as the last migration that (re)defines it leaves it.
        $migrations = glob(dirname(__DIR__, 3) . '/database/migrations/*.sql') ?: [];
        sort($migrations);
        $check = null;

        foreach ($migrations as $file) {
            if (preg_match('/bookings_status_check\s+CHECK\s*\(\s*status IN \(([^)]*)\)/', (string) file_get_contents($file), $match) === 1) {
                $check = $match[1];
            }
        }

        self::assertNotNull($check, 'no migration defines bookings_status_check');
        preg_match_all("/'([A-Z_]+)'/", $check, $values);

        $inDatabase = $values[1];
        $inEnum = array_map(static fn (BookingStatus $status): string => $status->value, BookingStatus::cases());
        sort($inDatabase);
        sort($inEnum);

        self::assertSame($inDatabase, $inEnum);
    }
}
