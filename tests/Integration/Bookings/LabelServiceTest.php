<?php

declare(strict_types=1);

namespace Tests\Integration\Bookings;

use App\Core\Database;
use App\Core\Logger;
use App\Domain\Booking;
use App\Domain\Enums\BookingStatus;
use App\Domain\User;
use App\Repositories\ApiCallRepository;
use App\Repositories\LabelRepository;
use App\Services\LabelService;
use App\Shiplogic\ShiplogicClient;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ShiplogicFixture;

final class LabelServiceTest extends BookingTestCase
{
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = $this->user('owner@example.test');
    }

    #[Test]
    public function aBookedShipmentsLabelIsAFreshLinkRecordedInTheHistory(): void
    {
        $booking = $this->booked();

        $outcome = $this->service([self::json(200, ShiplogicFixture::label())])->fetch($booking, $this->owner);

        self::assertStringStartsWith('https://labels.prod-shared.shiplogic.com/', (string) $outcome->url);
        self::assertSame([['ok', null]], $this->history($booking));
    }

    #[Test]
    public function aFailedFetchCanBeRetriedWithoutEverCreatingAnotherShipment(): void
    {
        $booking = $this->booked();

        // Retried once inside the client, then reported.
        $failed = $this->service([new Response(500), new Response(500)])->fetch($booking, $this->owner);
        self::assertNull($failed->url);
        self::assertSame('The courier is having problems right now.', $failed->message);
        self::assertSame(BookingStatus::Created, $this->reload($booking, $this->owner)->status);

        $retried = $this->service([self::json(200, ShiplogicFixture::label())])->fetch($booking, $this->owner);
        self::assertNotNull($retried->url);

        self::assertSame([['failed', 'http_500'], ['ok', null]], $this->history($booking));
        $calls = new ApiCallRepository(Database::fromConnection($this->db));
        self::assertSame(0, $calls->count($booking->id, ShiplogicClient::SHIPMENTS), 'label retries never create shipments');
        self::assertSame(3, $calls->count($booking->id, ShiplogicClient::LABEL));
    }

    #[Test]
    public function aBookingThatIsNotBookedHasNoLabel(): void
    {
        $booking = $this->moveTo($this->bookings->create($this->owner, $this->shipment(), $this->clock->now()), BookingStatus::Selected, $this->owner);

        // An empty queue: asking Shiplogic would throw.
        $outcome = $this->service([])->fetch($booking, $this->owner);

        self::assertNull($outcome->url);
        self::assertStringContainsString('not been booked', (string) $outcome->message);
    }

    #[Test]
    public function aCancelledShipmentHasNoLabel(): void
    {
        $booking = $this->moveTo($this->bookings->create($this->owner, $this->shipment(), $this->clock->now()), BookingStatus::Cancelled, $this->owner);

        $outcome = $this->service([])->fetch($booking, $this->owner);

        self::assertNull($outcome->url);
        self::assertStringContainsString('cancelled', (string) $outcome->message);
    }

    /**
     * @param list<mixed> $queue
     */
    private function service(array $queue): LabelService
    {
        $log = fopen('php://memory', 'w+b');
        self::assertIsResource($log);

        return new LabelService(new LabelRepository(Database::fromConnection($this->db)), $this->shiplogic($queue), new Logger($log));
    }

    private function booked(): Booking
    {
        return $this->moveTo($this->bookings->create($this->owner, $this->shipment(), $this->clock->now()), BookingStatus::Created, $this->owner);
    }

    /**
     * @return list<array{string, string|null}>
     */
    private function history(Booking $booking): array
    {
        $statement = $this->db->prepare('SELECT status, error_code FROM labels WHERE booking_id = ? ORDER BY id');
        $statement->execute([$booking->id]);

        return array_map(static fn (array $row): array => [$row['status'], $row['error_code']], $statement->fetchAll());
    }
}
