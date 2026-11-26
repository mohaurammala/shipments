<?php

declare(strict_types=1);

namespace Tests\Integration\Bookings;

use App\Core\Database;
use App\Core\Logger;
use App\Domain\Booking;
use App\Domain\Enums\BookingStatus;
use App\Domain\User;
use App\Repositories\QuoteRepository;
use App\Services\BookingDraftService;
use App\Services\Enums\QuoteResult;
use App\Services\Enums\SelectResult;
use App\Services\QuoteService;
use App\Shiplogic\RatesRequestMapper;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ShiplogicFixture;

final class QuoteServiceTest extends BookingTestCase
{
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = $this->user('owner@example.test');
    }

    #[Test]
    public function aQuoteIsStoredWithEveryDetailAndTheBookingBecomesQuoted(): void
    {
        $booking = $this->draft();

        $outcome = $this->service([self::json(200, ShiplogicFixture::rates())])->quote($booking);

        self::assertSame(QuoteResult::Quoted, $outcome->result);
        $saved = $this->reload($booking, $this->owner);
        self::assertSame(BookingStatus::Quoted, $saved->status);

        $quote = $this->service([])->currentQuote($saved);
        self::assertNotNull($quote);
        self::assertSame(['LOF', 'ECO'], array_map(static fn ($rate): string => $rate->serviceLevelCode, $quote->rates));
        self::assertSame(11625, $quote->rates[0]->total->cents);
        self::assertSame(10109, $quote->rates[0]->totalExcludingVat?->cents);
        self::assertSame(15.0, $quote->rates[0]->vatPercentage);
        self::assertSame('2026-11-26 14:00', $quote->rates[0]->deliveryFrom?->format('Y-m-d H:i'));
    }

    #[Test]
    public function noRatesKeepsTheBookingADraftAndInventsNothing(): void
    {
        $booking = $this->draft();

        $outcome = $this->service([self::json(200, ['message' => 'Success', 'rates' => []])])->quote($booking);

        self::assertSame(QuoteResult::NoRates, $outcome->result);
        self::assertSame(BookingStatus::Draft, $this->reload($booking, $this->owner)->status);
        self::assertNull($this->service([])->currentQuote($booking));
    }

    #[Test]
    public function aFailedQuoteExplainsItselfAndChangesNothing(): void
    {
        $booking = $this->draft();

        $outcome = $this->service([self::json(400, ['message' => 'collection postal_code is required'])])->quote($booking);

        self::assertSame(QuoteResult::Unavailable, $outcome->result);
        self::assertSame('The courier needs a postal code for both addresses.', $outcome->message);
        self::assertSame(BookingStatus::Draft, $this->reload($booking, $this->owner)->status);
    }

    #[Test]
    public function aQuoteForDetailsThatChangedMeanwhileDoesNotApply(): void
    {
        $loadedBeforeTheEdit = $this->draft();
        $this->bookings->replaceInput($loadedBeforeTheEdit, $this->shipment(['parcels.0.weight_kg' => '9']), $this->clock->now());

        $outcome = $this->service([self::json(200, ShiplogicFixture::rates())])->quote($loadedBeforeTheEdit);

        self::assertSame(QuoteResult::Stale, $outcome->result);
        self::assertSame(BookingStatus::Draft, $this->reload($loadedBeforeTheEdit, $this->owner)->status);
    }

    #[Test]
    public function aBookingThatIsBeingBookedIsNotQuotedAgain(): void
    {
        $booking = $this->moveTo($this->draft(), BookingStatus::Creating, $this->owner);

        // An empty queue: any call to Shiplogic would throw.
        self::assertSame(QuoteResult::Locked, $this->service([])->quote($booking)->result);
    }

    #[Test]
    public function aRateFromTheCurrentQuoteCanBeChosen(): void
    {
        $booking = $this->quoted();
        $eco = $this->service([])->currentQuote($booking)?->rates[1];

        self::assertSame(SelectResult::Selected, $this->service([])->select($booking, (int) $eco?->id));

        $saved = $this->reload($booking, $this->owner);
        self::assertSame(BookingStatus::Selected, $saved->status);
        self::assertSame($eco?->id, $saved->selectedRateId);
    }

    #[Test]
    public function aRateFromAnOlderQuoteCannotBeChosen(): void
    {
        $booking = $this->quoted();
        $old = $this->service([])->currentQuote($booking)?->rates[0];
        $this->service([self::json(200, ShiplogicFixture::rates())])->quote($this->reload($booking, $this->owner));

        self::assertSame(SelectResult::NotOffered, $this->service([])->select($this->reload($booking, $this->owner), (int) $old?->id));
    }

    #[Test]
    public function expiredPricesCannotBeChosen(): void
    {
        $booking = $this->quoted();
        $rate = $this->service([])->currentQuote($booking)?->rates[0];

        $this->clock->advance('+31 minutes');

        self::assertSame(SelectResult::Expired, $this->service([])->select($booking, (int) $rate?->id));
    }

    #[Test]
    public function quotingAgainClearsTheChosenService(): void
    {
        $booking = $this->quoted();
        $this->service([])->select($booking, (int) $this->service([])->currentQuote($booking)?->rates[0]->id);

        $this->service([self::json(200, ShiplogicFixture::rates())])->quote($this->reload($booking, $this->owner));

        $saved = $this->reload($booking, $this->owner);
        self::assertSame(BookingStatus::Quoted, $saved->status);
        self::assertNull($saved->selectedRateId);
    }

    /**
     * @param list<mixed> $queue
     */
    private function service(array $queue): QuoteService
    {
        $log = fopen('php://memory', 'w+b');
        self::assertIsResource($log);

        return new QuoteService(
            $this->bookings,
            new QuoteRepository(Database::fromConnection($this->db)),
            $this->shiplogic($queue),
            new RatesRequestMapper(),
            $this->clock,
            new Logger($log),
            ttlMinutes: 30,
        );
    }

    private function draft(): Booking
    {
        $log = fopen('php://memory', 'w+b');
        self::assertIsResource($log);

        return (new BookingDraftService($this->bookings, $this->clock, new Logger($log)))->create($this->owner, $this->shipment());
    }

    private function quoted(): Booking
    {
        $booking = $this->draft();
        $this->service([new Response(200, [], (string) json_encode(ShiplogicFixture::rates()))])->quote($booking);

        return $this->reload($booking, $this->owner);
    }
}
