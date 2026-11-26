<?php

declare(strict_types=1);

namespace Tests\Integration\Bookings;

use App\Core\Database;
use App\Domain\Booking;
use App\Domain\Enums\BookingStatus;
use App\Domain\ShipmentInput;
use App\Domain\User;
use App\Repositories\BookingRepository;
use App\Repositories\UserRepository;
use Tests\Integration\DatabaseTestCase;
use Tests\Support\FrozenClock;
use Tests\Support\ShipmentFixture;

/**
 * Shared set-up for booking tests: a repository on the test connection, a clock the test
 * controls, and helpers to put a booking into any status.
 */
abstract class BookingTestCase extends DatabaseTestCase
{
    protected BookingRepository $bookings;

    protected UserRepository $users;

    protected FrozenClock $clock;

    protected function setUp(): void
    {
        parent::setUp();
        $database = Database::fromConnection($this->db);
        $this->bookings = new BookingRepository($database);
        $this->users = new UserRepository($database);
        $this->clock = new FrozenClock();
    }

    /**
     * A Shiplogic client answering from $queue in order (Guzzle's MockHandler: no network).
     * Every request it receives is appended to $requests.
     *
     * @param list<mixed> $queue Responses or exceptions.
     * @param list<array{request: \Psr\Http\Message\RequestInterface}> $requests
     */
    protected function shiplogic(array $queue, array &$requests = []): \App\Shiplogic\ShiplogicClient
    {
        $stack = \GuzzleHttp\HandlerStack::create(new \GuzzleHttp\Handler\MockHandler($queue));
        $stack->push(\GuzzleHttp\Middleware::history($requests));
        $http = new \GuzzleHttp\Client(['handler' => $stack, 'base_uri' => 'https://api.shiplogic.test/', 'http_errors' => true]);
        $log = fopen('php://memory', 'w+b');
        self::assertIsResource($log);

        return new \App\Shiplogic\ShiplogicClient(
            $http,
            new \App\Repositories\ApiCallRepository(Database::fromConnection($this->db)),
            new \App\Core\Logger($log),
            retryDelayMs: 0,
        );
    }

    /**
     * @param array<string, mixed> $body
     */
    protected static function json(int $status, array $body): \GuzzleHttp\Psr7\Response
    {
        return new \GuzzleHttp\Psr7\Response($status, ['Content-Type' => 'application/json'], json_encode($body, JSON_THROW_ON_ERROR));
    }

    protected function reload(Booking $booking, User $owner): Booking
    {
        return $this->bookings->findForUser($booking->publicId, $owner) ?? self::fail('Booking vanished.');
    }

    protected function user(string $email, string $role = 'operator'): User
    {
        return $this->users->findById($this->createUser($email, $role)) ?? self::fail("User {$email} not created.");
    }

    /**
     * @param array<string, mixed> $changes Dotted paths into the fixture, e.g. "parcels.0.weight_kg".
     */
    protected function shipment(array $changes = []): ShipmentInput
    {
        $input = ShipmentFixture::input();

        foreach ($changes as $path => $value) {
            $target = &$input;

            foreach (explode('.', $path) as $key) {
                $target = &$target[$key];
            }

            $target = $value;
            unset($target);
        }

        return ShipmentInput::fromArray($input);
    }

    /**
     * Moves a booking straight to a status, with what that status requires (a rate, a
     * shipment id), as later features will. Returns it as it is now stored.
     */
    protected function moveTo(Booking $booking, BookingStatus $status, User $owner): Booking
    {
        $needsRate = !in_array($status, [BookingStatus::Draft, BookingStatus::Quoted], true);
        $rateId = $needsRate ? $this->createRate($booking->id) : null;
        $shipmentId = in_array($status, [BookingStatus::Created, BookingStatus::Cancelled], true) ? 'SL-' . $booking->id : null;

        $this->db->prepare('UPDATE bookings SET status = ?, selected_rate_id = ?, shiplogic_shipment_id = ? WHERE id = ?')
            ->execute([$status->value, $rateId, $shipmentId, $booking->id]);

        return $this->bookings->findForUser($booking->publicId, $owner) ?? self::fail('Booking vanished.');
    }
}
