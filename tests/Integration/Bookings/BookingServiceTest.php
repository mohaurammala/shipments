<?php

declare(strict_types=1);

namespace Tests\Integration\Bookings;

use App\Core\Database;
use App\Core\Logger;
use App\Domain\Booking;
use App\Domain\Enums\BookingStatus;
use App\Domain\User;
use App\Repositories\ApiCallRepository;
use App\Repositories\QuoteRepository;
use App\Services\BookingDraftService;
use App\Services\BookingService;
use App\Services\Enums\BookResult;
use App\Services\QuoteService;
use App\Shiplogic\RatesRequestMapper;
use App\Shiplogic\ShiplogicClient;
use App\Shiplogic\ShipmentRequestMapper;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\NetworkTimeoutException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ShiplogicFixture;

/**
 * Booking a shipment and its duplicate protection, against the test database with Shiplogic simulated.
 */
final class BookingServiceTest extends BookingTestCase
{
    private User $owner;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = $this->user('owner@example.test');
        $this->admin = $this->user('admin@example.test', 'admin');
    }

    #[Test]
    public function aSelectedBookingIsBookedOnceWithItsServiceAndReference(): void
    {
        $booking = $this->selected();
        $requests = [];

        $outcome = $this->service([self::json(200, ShiplogicFixture::createdShipment(14135))], $requests)->book($booking);

        self::assertSame(BookResult::Booked, $outcome->result);
        $saved = $this->reload($booking, $this->owner);
        self::assertSame(BookingStatus::Created, $saved->status);
        self::assertSame('14135', $saved->shipmentId);
        self::assertSame('G9GXYZ', $saved->trackingReference);

        $sent = json_decode((string) $requests[0]['request']->getBody(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('LOF', $sent['service_level_code']);
        self::assertSame($booking->publicId, $sent['customer_reference']);
        self::assertStringContainsString('SANDBOX TEST ONLY - DO NOT DELIVER', $sent['special_instructions_delivery']);
        self::assertTrue($sent['mute_notifications']);
    }

    #[Test]
    public function aBookedShipmentIsNeverBookedAgain(): void
    {
        $booking = $this->selected();
        $this->service([self::json(200, ShiplogicFixture::createdShipment())])->book($booking);

        // An empty queue: a second call to Shiplogic would throw.
        $again = $this->service([])->book($this->reload($booking, $this->owner));

        self::assertSame(BookResult::NotReady, $again->result);
        self::assertSame(1, $this->shipmentCalls($booking));
    }

    #[Test]
    public function ofTwoRequestsForTheSameBookingOnlyOneReachesShiplogic(): void
    {
        // Two tabs (or a double click) both loaded the booking while it was SELECTED.
        $first = $this->selected();
        $second = $this->reload($first, $this->owner);

        $winner = $this->service([self::json(200, ShiplogicFixture::createdShipment())])->book($first);
        $loser = $this->service([])->book($second);

        self::assertSame(BookResult::Booked, $winner->result);
        self::assertSame(BookResult::AlreadyInProgress, $loser->result);
        self::assertSame(1, $this->shipmentCalls($first));
    }

    #[Test]
    public function aRejectionFailsTheBookingWithAReasonAndAllowsAnotherTry(): void
    {
        $booking = $this->selected();

        $outcome = $this->service([self::json(400, ['message' => 'parcel exceeds the maximum weight of 30 kg'])])->book($booking);

        self::assertSame(BookResult::Failed, $outcome->result);
        $failed = $this->reload($booking, $this->owner);
        self::assertSame(BookingStatus::Failed, $failed->status);
        self::assertSame('A parcel is heavier than this courier accepts.', $failed->failureReason);

        $retry = $this->service([self::json(200, ShiplogicFixture::createdShipment())])->book($failed);
        self::assertSame(BookResult::Booked, $retry->result);
        self::assertNull($this->reload($booking, $this->owner)->failureReason);
    }

    #[Test]
    public function noConnectionMeansNothingWasBookedAndTryingAgainIsSafe(): void
    {
        $booking = $this->selected();

        $outcome = $this->service([new ConnectException('refused', new Request('POST', 'shipments'))])->book($booking);

        self::assertSame(BookResult::Failed, $outcome->result);
        self::assertSame(BookingStatus::Failed, $this->reload($booking, $this->owner)->status);
    }

    #[Test]
    public function aTimeoutLeavesTheOutcomeUnknownAndBlocksBookingAgain(): void
    {
        $booking = $this->selected();

        $outcome = $this->service([new NetworkTimeoutException('timed out', new Request('POST', 'shipments'))])->book($booking);

        self::assertSame(BookResult::OutcomeUnknown, $outcome->result);
        $unknown = $this->reload($booking, $this->owner);
        self::assertSame(BookingStatus::OutcomeUnknown, $unknown->status);

        self::assertSame(BookResult::NotReady, $this->service([])->book($unknown)->result);
        self::assertSame(1, $this->shipmentCalls($booking), 'never resubmitted automatically');
    }

    #[Test]
    public function aServerErrorLeavesTheOutcomeUnknown(): void
    {
        $booking = $this->selected();

        self::assertSame(BookResult::OutcomeUnknown, $this->service([new Response(500)])->book($booking)->result);
    }

    #[Test]
    public function aSuccessWithoutAShipmentIdLeavesTheOutcomeUnknown(): void
    {
        $booking = $this->selected();

        self::assertSame(BookResult::OutcomeUnknown, $this->service([self::json(200, ['status' => 'submitted'])])->book($booking)->result);
    }

    #[Test]
    public function anExpiredQuoteMustBeRenewedFirst(): void
    {
        $booking = $this->selected();
        $this->clock->advance('+31 minutes');

        $outcome = $this->service([])->book($booking);

        self::assertSame(BookResult::QuoteExpired, $outcome->result);
        self::assertSame(BookingStatus::Selected, $this->reload($booking, $this->owner)->status);
    }

    #[Test]
    public function aBookingAbandonedInCreatingIsTreatedAsUnknownOnlyOnceItIsStale(): void
    {
        $booking = $this->moveTo($this->selected(), BookingStatus::Creating, $this->owner);

        $this->clock->advance('+30 seconds');
        self::assertFalse($this->service([])->settleIfStuck($this->reload($booking, $this->owner)));

        $this->clock->advance('+1 minute');
        self::assertTrue($this->service([])->settleIfStuck($this->reload($booking, $this->owner)));
        self::assertSame(BookingStatus::OutcomeUnknown, $this->reload($booking, $this->owner)->status);
    }

    #[Test]
    public function anAdminConfirmsAShipmentFoundInThePortal(): void
    {
        $unknown = $this->unknown();

        $outcome = $this->service([])->resolve($unknown, $this->admin, '14135', 'G9GXYZ');

        self::assertSame(BookResult::Booked, $outcome->result);
        self::assertSame(BookingStatus::Created, $this->reload($unknown, $this->owner)->status);
        self::assertSame('14135', $this->reload($unknown, $this->owner)->shipmentId);
    }

    #[Test]
    public function anAdminConfirmsNothingWasBookedSoItCanBeBookedAgain(): void
    {
        $unknown = $this->unknown();

        $this->service([])->resolve($unknown, $this->admin, null, null);

        self::assertSame(BookingStatus::Failed, $this->reload($unknown, $this->owner)->status);
    }

    #[Test]
    public function oneShipmentIdCannotBeClaimedForTwoBookings(): void
    {
        $booked = $this->selected();
        $this->service([self::json(200, ShiplogicFixture::createdShipment(14135))])->book($booked);

        $outcome = $this->service([])->resolve($this->unknown(), $this->admin, '14135', null);

        self::assertSame(BookResult::NotReady, $outcome->result);
        self::assertStringContainsString('another booking', $outcome->message);
    }

    #[Test]
    public function aBookedShipmentIsCancelledByItsTrackingReferenceAndKeepsItsIds(): void
    {
        $booking = $this->booked();
        $requests = [];

        $outcome = $this->service([self::json(200, ShiplogicFixture::cancelledShipment(14135))], $requests)->cancel($booking);

        self::assertSame(BookResult::Cancelled, $outcome->result);
        $cancelled = $this->reload($booking, $this->owner);
        self::assertSame(BookingStatus::Cancelled, $cancelled->status);
        self::assertSame('14135', $cancelled->shipmentId);
        self::assertSame('G9GXYZ', $cancelled->trackingReference);
        self::assertSame(['tracking_reference' => 'G9GXYZ'], json_decode((string) $requests[0]['request']->getBody(), true, flags: JSON_THROW_ON_ERROR));
    }

    #[Test]
    public function aCancelledShipmentIsNotCancelledAgain(): void
    {
        $booking = $this->booked();
        $this->service([self::json(200, ShiplogicFixture::cancelledShipment())])->cancel($booking);

        // An empty queue: a second call to Shiplogic would throw.
        $again = $this->service([])->cancel($this->reload($booking, $this->owner));

        self::assertSame(BookResult::NotReady, $again->result);
        self::assertStringContainsString('already been cancelled', $again->message);
    }

    #[Test]
    public function onlyABookedShipmentCanBeCancelled(): void
    {
        $outcome = $this->service([])->cancel($this->selected());

        self::assertSame(BookResult::NotReady, $outcome->result);
    }

    #[Test]
    public function withoutATrackingReferenceNothingIsSent(): void
    {
        $booking = $this->moveTo($this->selected(), BookingStatus::Created, $this->owner);

        $outcome = $this->service([])->cancel($booking);

        self::assertSame(BookResult::NotReady, $outcome->result);
        self::assertStringContainsString('Shiplogic portal', $outcome->message);
    }

    #[Test]
    public function aRefusedCancellationLeavesTheShipmentBooked(): void
    {
        $booking = $this->booked();

        $outcome = $this->service([self::json(400, ['message' => 'shipment cannot be cancelled'])])->cancel($booking);

        self::assertSame(BookResult::Failed, $outcome->result);
        self::assertSame(BookingStatus::Created, $this->reload($booking, $this->owner)->status);
    }

    #[Test]
    public function aTimedOutCancellationLeavesTheShipmentBookedAndCanBeTriedAgain(): void
    {
        $booking = $this->booked();

        $outcome = $this->service([new NetworkTimeoutException('timed out', new Request('POST', 'shipments/cancel'))])->cancel($booking);

        self::assertSame(BookResult::OutcomeUnknown, $outcome->result);
        $still = $this->reload($booking, $this->owner);
        self::assertSame(BookingStatus::Created, $still->status);

        $retry = $this->service([self::json(200, ShiplogicFixture::cancelledShipment())])->cancel($still);
        self::assertSame(BookResult::Cancelled, $retry->result);
    }

    #[Test]
    public function aSuccessThatDoesNotSayCancelledLeavesTheShipmentBooked(): void
    {
        $booking = $this->booked();

        $outcome = $this->service([self::json(200, ['id' => 14135, 'status' => 'collected'])])->cancel($booking);

        self::assertSame(BookResult::OutcomeUnknown, $outcome->result);
        self::assertSame(BookingStatus::Created, $this->reload($booking, $this->owner)->status);
    }

    #[Test]
    public function ignoringTheResultOfAClaimIsReportedByPhp(): void
    {
        $booking = $this->selected();
        $warnings = [];
        set_error_handler(static function (int $level, string $message) use (&$warnings): bool {
            $warnings[] = $message;

            return true;
        });

        try {
            $this->bookings->claim($booking, $this->clock->now());
        } finally {
            restore_error_handler();
        }

        self::assertCount(1, $warnings);
        self::assertStringContainsString('claim()', $warnings[0]);
    }

    /**
     * @param list<mixed> $queue
     * @param list<array{request: \Psr\Http\Message\RequestInterface}> $requests
     */
    private function service(array $queue, array &$requests = []): BookingService
    {
        $client = $this->shiplogic($queue, $requests);

        return new BookingService(
            $this->bookings,
            $this->quotes($client),
            $client,
            new ShipmentRequestMapper(isTestEnvironment: true),
            $this->clock,
            $this->logger(),
            stuckAfterSeconds: 55,
        );
    }

    private function quotes(ShiplogicClient $client): QuoteService
    {
        return new QuoteService(
            $this->bookings,
            new QuoteRepository(Database::fromConnection($this->db)),
            $client,
            new RatesRequestMapper(),
            $this->clock,
            $this->logger(),
            ttlMinutes: 30,
        );
    }

    /**
     * A booking with a fresh quote and its first rate (LOF) chosen.
     */
    private function selected(): Booking
    {
        $booking = (new BookingDraftService($this->bookings, $this->clock, $this->logger()))->create($this->owner, $this->shipment());
        $quotes = $this->quotes($this->shiplogic([self::json(200, ShiplogicFixture::rates())]));
        $quotes->quote($booking);
        $quoted = $this->reload($booking, $this->owner);
        $quotes->select($quoted, (int) $quotes->currentQuote($quoted)?->rates[0]->id);

        return $this->reload($booking, $this->owner);
    }

    /**
     * A booking created through the service, so it has Shiplogic's tracking reference.
     */
    private function booked(): Booking
    {
        $booking = $this->selected();
        $this->service([self::json(200, ShiplogicFixture::createdShipment(14135))])->book($booking);

        return $this->reload($booking, $this->owner);
    }

    private function unknown(): Booking
    {
        $booking = $this->selected();
        $this->service([new NetworkTimeoutException('timed out', new Request('POST', 'shipments'))])->book($booking);

        return $this->reload($booking, $this->owner);
    }

    private function shipmentCalls(Booking $booking): int
    {
        return (new ApiCallRepository(Database::fromConnection($this->db)))->count($booking->id, ShiplogicClient::SHIPMENTS);
    }

    private function logger(): Logger
    {
        $log = fopen('php://memory', 'w+b');
        self::assertIsResource($log);

        return new Logger($log);
    }
}
