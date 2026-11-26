<?php

declare(strict_types=1);

namespace Tests\Integration\Shiplogic;

use App\Core\Config;
use App\Core\Database;
use App\Core\Logger;
use App\Repositories\ApiCallRepository;
use App\Shiplogic\Exceptions\BadResponse;
use App\Shiplogic\Exceptions\HttpError;
use App\Shiplogic\Exceptions\NetworkFailure;
use App\Shiplogic\ShiplogicClient;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\NetworkTimeoutException;
use GuzzleHttp\Exception\ResponseTimeoutException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PDO;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\RequestInterface;
use Tests\Integration\DatabaseTestCase;
use Tests\Support\ShiplogicFixture;

/**
 * Against Guzzle's MockHandler (no network), and the test database for api_calls.
 */
final class ShiplogicClientTest extends DatabaseTestCase
{
    /** @var resource */
    private $log;

    protected function setUp(): void
    {
        parent::setUp();
        $log = fopen('php://memory', 'w+b');
        self::assertIsResource($log);
        $this->log = $log;
    }

    #[Test]
    public function everyRequestCarriesTheKeyTheTimeoutsAndAJsonBody(): void
    {
        $seen = [];
        $handler = new MockHandler([
            static function (RequestInterface $request, array $options) use (&$seen): Response {
                $seen = [$request, $options];

                return self::json(200, ShiplogicFixture::rates());
            },
        ]);
        $config = new Config([
            'SHIPLOGIC_BASE_URL' => 'https://api.shiplogic.com',
            'SHIPLOGIC_API_KEY' => 'test-key',
            'SHIPLOGIC_CONNECT_TIMEOUT' => '5',
            'SHIPLOGIC_TIMEOUT' => '20',
        ]);

        ShiplogicClient::fromConfig($config, $this->calls(), new Logger($this->log), $handler)->getRates(['parcels' => []], null);

        [$request, $options] = $seen;
        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://api.shiplogic.com/rates', (string) $request->getUri());
        self::assertSame('Bearer test-key', $request->getHeaderLine('Authorization'));
        self::assertSame('{"parcels":[]}', (string) $request->getBody());
        self::assertSame(5, $options['connect_timeout']);
        self::assertSame(20, $options['timeout']);
    }

    #[Test]
    public function ratesAreReadAndTheCallIsRecordedWithWhatWentOutAndCameBack(): void
    {
        $rates = $this->client([self::json(200, ShiplogicFixture::rates())])->getRates(['parcels' => []], $bookingId = $this->booking());

        self::assertCount(2, $rates->rates);
        self::assertSame([['POST', '/rates', 200, 'ok']], $this->recorded($bookingId));
        [[$sent, $received]] = $this->bodies($bookingId);
        self::assertSame('{"parcels":[]}', $sent);
        self::assertSame(ShiplogicFixture::rates(), json_decode((string) $received, true, flags: JSON_THROW_ON_ERROR));
    }

    #[Test]
    public function aRejectionIsRecordedWithShiplogicsAnswer(): void
    {
        try {
            $this->client([self::json(400, ['message' => 'parcel exceeds the maximum weight of 30 kg'])])->createShipment(['parcels' => []], $bookingId = $this->booking());
            self::fail('Expected an HttpError.');
        } catch (HttpError) {
        }

        self::assertSame([['{"parcels":[]}', '{"message":"parcel exceeds the maximum weight of 30 kg"}']], $this->bodies($bookingId));
    }

    #[Test]
    public function aVeryLargeAnswerIsCut(): void
    {
        $this->client([self::json(200, ['id' => 1, 'status' => 'cancelled', 'padding' => str_repeat('x', 300_000)])])
            ->cancelShipment('G9GXYZ', $bookingId = $this->booking());

        [[, $received]] = $this->bodies($bookingId);
        self::assertLessThan(300_000, strlen((string) $received));
        self::assertStringEndsWith('…[cut]', (string) $received);
    }

    #[Test]
    public function readingRatesIsRetriedOnceAfterAServerError(): void
    {
        $client = $this->client([new Response(503), self::json(200, ShiplogicFixture::rates())]);

        self::assertCount(2, $client->getRates([], $bookingId = $this->booking())->rates);
        self::assertSame([['POST', '/rates', 503, 'http_error'], ['POST', '/rates', 200, 'ok']], $this->recorded($bookingId));
    }

    #[Test]
    public function creatingAShipmentIsNeverRetried(): void
    {
        $client = $this->client([new Response(503), self::json(200, ShiplogicFixture::createdShipment())]);

        try {
            $client->createShipment([], $bookingId = $this->booking());
            self::fail('Expected an HttpError.');
        } catch (HttpError $e) {
            self::assertSame(503, $e->status);
            self::assertTrue($e->mayHaveReachedServer(), 'a 5xx may come after the shipment was saved');
        }

        self::assertCount(1, $this->recorded($bookingId));
    }

    #[Test]
    public function aCancellationSendsOnlyTheTrackingReference(): void
    {
        $seen = null;
        $client = $this->client([
            static function (RequestInterface $request) use (&$seen): Response {
                $seen = $request;

                return self::json(200, ShiplogicFixture::cancelledShipment(14135));
            },
        ]);

        $cancelled = $client->cancelShipment('G9GXYZ', $bookingId = $this->booking());

        self::assertSame('14135', $cancelled->id);
        self::assertInstanceOf(RequestInterface::class, $seen);
        self::assertSame('POST', $seen->getMethod());
        self::assertSame('/shipments/cancel', $seen->getUri()->getPath());
        self::assertSame(['tracking_reference' => 'G9GXYZ'], json_decode((string) $seen->getBody(), true, flags: JSON_THROW_ON_ERROR));
        self::assertSame([['POST', '/shipments/cancel', 200, 'ok']], $this->recorded($bookingId));
    }

    #[Test]
    public function aCancellationIsNotRetriedAutomatically(): void
    {
        $client = $this->client([new Response(503), self::json(200, ShiplogicFixture::cancelledShipment())]);

        try {
            $client->cancelShipment('G9GXYZ', $bookingId = $this->booking());
            self::fail('Expected an HttpError.');
        } catch (HttpError $e) {
            self::assertSame(503, $e->status);
        }

        self::assertCount(1, $this->recorded($bookingId));
    }

    #[Test]
    public function aRejectionKeepsShiplogicsMessageForMappingOnly(): void
    {
        $client = $this->client([self::json(400, ['message' => 'parcel exceeds the maximum weight of 30 kg'])]);

        try {
            $client->createShipment([], null);
            self::fail('Expected an HttpError.');
        } catch (HttpError $e) {
            self::assertTrue($e->isRejection());
            self::assertFalse($e->mayHaveReachedServer());
            self::assertSame('parcel exceeds the maximum weight of 30 kg', $e->apiMessage);
            self::assertStringNotContainsString('maximum weight', $e->getMessage(), 'the exception message stays generic');
        }
    }

    #[Test]
    public function noConnectionMeansTheShipmentCannotExist(): void
    {
        $client = $this->client([new ConnectException('refused', new Request('POST', 'shipments'))]);

        try {
            $client->createShipment([], $bookingId = $this->booking());
            self::fail('Expected a NetworkFailure.');
        } catch (NetworkFailure $e) {
            self::assertFalse($e->requestSent);
            self::assertFalse($e->mayHaveReachedServer());
        }

        self::assertSame([['POST', '/shipments', null, 'network']], $this->recorded($bookingId));
    }

    #[Test]
    public function aTimeoutAfterSendingMeansTheShipmentMayExist(): void
    {
        $client = $this->client([new NetworkTimeoutException('timed out', new Request('POST', 'shipments'))]);

        try {
            $client->createShipment([], $bookingId = $this->booking());
            self::fail('Expected a NetworkFailure.');
        } catch (NetworkFailure $e) {
            self::assertTrue($e->requestSent);
            self::assertTrue($e->timedOut);
            self::assertTrue($e->mayHaveReachedServer());
        }

        self::assertSame([['POST', '/shipments', null, 'timeout']], $this->recorded($bookingId));
        self::assertSame([['[]', null]], $this->bodies($bookingId), 'what went out is kept; nothing came back');
    }

    #[Test]
    public function aBodyThatNeverArrivesAfterASuccessStatusIsUncertainToo(): void
    {
        $request = new Request('POST', 'shipments');
        $client = $this->client([new ResponseTimeoutException('body timed out', $request, new Response(200))]);

        try {
            $client->createShipment([], $bookingId = $this->booking());
            self::fail('Expected a NetworkFailure.');
        } catch (NetworkFailure $e) {
            self::assertTrue($e->mayHaveReachedServer());
        }

        self::assertSame([['POST', '/shipments', 200, 'timeout']], $this->recorded($bookingId));
    }

    #[Test]
    public function aSuccessWithoutAnIdIsABadResponse(): void
    {
        $this->expectException(BadResponse::class);

        $this->client([self::json(201, ['status' => 'submitted'])])->createShipment([], null);
    }

    #[Test]
    public function aBodyThatIsNotJsonIsABadResponse(): void
    {
        $client = $this->client([new Response(200, [], '<html>Gateway</html>')]);

        try {
            $client->getRates([], $bookingId = $this->booking());
            self::fail('Expected a BadResponse.');
        } catch (BadResponse) {
        }

        self::assertSame([['POST', '/rates', 200, 'bad_response']], $this->recorded($bookingId));
        self::assertSame('<html>Gateway</html>', $this->bodies($bookingId)[0][1], 'kept as it came, to show what was wrong');
    }

    #[Test]
    public function aCreatedShipmentIsReturned(): void
    {
        $created = $this->client([self::json(200, ShiplogicFixture::createdShipment(14135))])->createShipment([], null);

        self::assertSame('14135', $created->id);
    }

    #[Test]
    public function theLabelIsASignedHttpsLinkAndIsRecordedWithoutItsSignature(): void
    {
        $url = $this->client([self::json(200, ShiplogicFixture::label())])->getLabelUrl('14135', $bookingId = $this->booking());

        self::assertStringStartsWith('https://labels.prod-shared.shiplogic.com/', $url);
        self::assertStringContainsString('Signature=', $url, 'the caller still gets the working link');
        self::assertSame([['GET', '/shipments/label/stickers', 200, 'ok']], $this->recorded($bookingId));

        [[$sent, $received]] = $this->bodies($bookingId);
        self::assertSame('{"id":"14135"}', $sent);
        self::assertStringNotContainsString('Signature', (string) $received);
        self::assertStringNotContainsString('Expires', (string) $received);
        self::assertSame(
            'https://labels.prod-shared.shiplogic.com/sandbox_Stickers_1_ABC.pdf?[removed]',
            json_decode((string) $received, true, flags: JSON_THROW_ON_ERROR)['url'],
        );
    }

    #[Test]
    public function theApiKeyIsNeverRecorded(): void
    {
        $config = new Config(['SHIPLOGIC_BASE_URL' => 'https://api.shiplogic.test', 'SHIPLOGIC_API_KEY' => 'secret-test-key']);
        $client = ShiplogicClient::fromConfig($config, $this->calls(), new Logger($this->log), new MockHandler([self::json(200, ShiplogicFixture::rates())]));

        $client->getRates(['parcels' => []], $bookingId = $this->booking());

        $row = $this->db->query("SELECT * FROM api_calls WHERE booking_id = {$bookingId}")->fetch();
        self::assertStringNotContainsString('secret-test-key', (string) json_encode($row));
    }

    #[Test]
    public function aLabelLinkThatIsNotHttpsIsRefused(): void
    {
        $this->expectException(BadResponse::class);

        $this->client([self::json(200, ['url' => 'http://example.test/label.pdf'])])->getLabelUrl('1', null);
    }

    #[Test]
    public function failingToRecordACallNeverLosesItsResult(): void
    {
        // A database with no api_calls table: every record() fails.
        $calls = new ApiCallRepository(Database::fromConnection(new PDO('sqlite::memory:')));
        $client = new ShiplogicClient($this->http([self::json(200, ShiplogicFixture::createdShipment(14135))]), $calls, new Logger($this->log), 0);

        self::assertSame('14135', $client->createShipment([], null)->id);

        rewind($this->log);
        self::assertStringContainsString('Could not record a Shiplogic call', (string) stream_get_contents($this->log));
    }

    /**
     * @param list<mixed> $queue
     */
    private function client(array $queue): ShiplogicClient
    {
        return new ShiplogicClient($this->http($queue), $this->calls(), new Logger($this->log), retryDelayMs: 0);
    }

    /**
     * @param list<mixed> $queue
     */
    private function http(array $queue): Client
    {
        return new Client([
            'handler' => HandlerStack::create(new MockHandler($queue)),
            'base_uri' => 'https://api.shiplogic.test/',
            'http_errors' => true,
        ]);
    }

    private function calls(): ApiCallRepository
    {
        return new ApiCallRepository(Database::fromConnection($this->db));
    }

    private function booking(): int
    {
        return $this->createBooking($this->createUser());
    }

    /**
     * @return list<array{string, string, int|null, string}>
     */
    private function recorded(int $bookingId): array
    {
        $statement = $this->db->prepare('SELECT method, endpoint, http_status, outcome FROM api_calls WHERE booking_id = ? ORDER BY id');
        $statement->execute([$bookingId]);

        return array_map(
            static fn (array $row): array => [$row['method'], $row['endpoint'], $row['http_status'], $row['outcome']],
            $statement->fetchAll(),
        );
    }

    /**
     * @return list<array{string|null, string|null}>
     */
    private function bodies(int $bookingId): array
    {
        $statement = $this->db->prepare('SELECT request_body, response_body FROM api_calls WHERE booking_id = ? ORDER BY id');
        $statement->execute([$bookingId]);

        return array_map(static fn (array $row): array => [$row['request_body'], $row['response_body']], $statement->fetchAll());
    }

    /**
     * @param array<string, mixed> $body
     */
    private static function json(int $status, array $body): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'], json_encode($body, JSON_THROW_ON_ERROR));
    }
}
