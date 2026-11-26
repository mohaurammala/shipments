<?php

declare(strict_types=1);

namespace App\Shiplogic;

use App\Core\Config;
use App\Core\Logger;
use App\Repositories\ApiCallRepository;
use App\Shiplogic\Exceptions\BadResponse;
use App\Shiplogic\Exceptions\HttpError;
use App\Shiplogic\Exceptions\NetworkFailure;
use App\Shiplogic\Exceptions\ShiplogicException;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\NetworkException;
use GuzzleHttp\Exception\NetworkTimeoutException;
use GuzzleHttp\Exception\ResponseException;
use GuzzleHttp\Exception\ResponseTimeoutException;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\RequestOptions;
use JsonException;
use Throwable;

/**
 * The only code that talks to Shiplogic. It holds the API key (never logged, never sent to
 * the browser), applies the timeouts, records every call in api_calls with what went out and
 * what came back, and turns every kind of failure into one of three exceptions whose type
 * decides what happens to a booking.
 *
 * Retries: reading rates and labels is retried once after a network failure or a 5xx.
 * Creating a shipment is never retried: a second attempt after an unclear failure is how
 * duplicate shipments happen.
 */
final readonly class ShiplogicClient
{
    public const string RATES = '/rates';
    public const string SHIPMENTS = '/shipments';
    public const string SHIPMENTS_CANCEL = '/shipments/cancel';
    public const string LABEL = '/shipments/label/stickers';

    /** A cancelled shipment comes back whole (about 8 KB); this leaves room to spare. */
    private const int MAX_LOGGED_BYTES = 256 * 1024;
    private const int JSON_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE;

    public function __construct(
        private ClientInterface $http,
        private ApiCallRepository $calls,
        private Logger $logger,
        private int $retryDelayMs = 300,
    ) {
    }

    /**
     * @param callable|null $handler A Guzzle handler, e.g. a MockHandler in tests.
     */
    public static function fromConfig(Config $config, ApiCallRepository $calls, Logger $logger, ?callable $handler = null): self
    {
        $options = [
            'base_uri' => rtrim($config->get('SHIPLOGIC_BASE_URL', 'https://api.shiplogic.com'), '/') . '/',
            RequestOptions::CONNECT_TIMEOUT => $config->int('SHIPLOGIC_CONNECT_TIMEOUT', 5),
            RequestOptions::TIMEOUT => $config->int('SHIPLOGIC_TIMEOUT', 20),
            RequestOptions::HTTP_ERRORS => true,
            RequestOptions::HEADERS => [
                'Authorization' => 'Bearer ' . $config->get('SHIPLOGIC_API_KEY', ''),
                'Accept' => 'application/json',
            ],
        ];

        if ($handler !== null) {
            $options['handler'] = HandlerStack::create($handler);
        }

        return new self(new Client($options), $calls, $logger);
    }

    /**
     * @param array<string, mixed> $payload From RatesRequestMapper.
     */
    public function getRates(array $payload, ?int $bookingId): RatesResponse
    {
        $data = $this->send('POST', self::RATES, [RequestOptions::JSON => $payload], $bookingId, retry: true);

        return RatesResponse::fromArray($data);
    }

    /**
     * Never retried. The caller decides what an exception means for the booking.
     *
     * @param array<string, mixed> $payload From ShipmentRequestMapper.
     */
    public function createShipment(array $payload, ?int $bookingId): CreatedShipment
    {
        $data = $this->send('POST', self::SHIPMENTS, [RequestOptions::JSON => $payload], $bookingId, retry: false);

        return CreatedShipment::fromArray($data);
    }

    /**
     * Cancels a booked shipment, found by its tracking reference. Not retried here: the user
     * sees the result and can try again, and cancelling twice does no harm.
     */
    public function cancelShipment(string $trackingReference, ?int $bookingId): CancelledShipment
    {
        $payload = ['tracking_reference' => $trackingReference];
        $data = $this->send('POST', self::SHIPMENTS_CANCEL, [RequestOptions::JSON => $payload], $bookingId, retry: false);

        return CancelledShipment::fromArray($data);
    }

    /**
     * A signed URL to the label PDF. Fetched fresh every time: the link expires.
     */
    public function getLabelUrl(string $shipmentId, ?int $bookingId): string
    {
        $data = $this->send('GET', self::LABEL, [RequestOptions::QUERY => ['id' => $shipmentId]], $bookingId, retry: true);
        $url = $data['url'] ?? null;

        if (!is_string($url) || !str_starts_with($url, 'https://')) {
            throw new BadResponse('the label response has no https url.');
        }

        return $url;
    }

    /**
     * @param array<string, mixed> $options
     * @return array<array-key, mixed>
     */
    private function send(string $method, string $endpoint, array $options, ?int $bookingId, bool $retry): array
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                return $this->attempt($method, $endpoint, $options, $bookingId);
            } catch (ShiplogicException $e) {
                if (!$retry || $attempt >= 2 || !$e->isRetryable()) {
                    throw $e;
                }

                usleep($this->retryDelayMs * 1000);
            }
        }
    }

    /**
     * @param array<string, mixed> $options
     * @return array<array-key, mixed>
     */
    private function attempt(string $method, string $endpoint, array $options, ?int $bookingId): array
    {
        $started = hrtime(true);
        $sent = self::sentBody($options);
        $record = function (?int $status, string $outcome, ?string $received = null) use ($bookingId, $method, $endpoint, $started, $sent): void {
            $this->record($bookingId, $method, $endpoint, $status, intdiv(hrtime(true) - $started, 1_000_000), $outcome, $sent, $received);
        };

        try {
            $response = $this->http->request($method, ltrim($endpoint, '/'), $options);
        } catch (ConnectException) {
            // No connection was made: Shiplogic cannot have seen this request.
            $record(null, 'network');

            throw new NetworkFailure(requestSent: false, timedOut: false);
        } catch (NetworkException $e) {
            // Sent, or partly sent, with no response: Shiplogic may have acted on it.
            $timedOut = $e instanceof NetworkTimeoutException;
            $record(null, $timedOut ? 'timeout' : 'network');

            throw new NetworkFailure(requestSent: true, timedOut: $timedOut);
        } catch (BadResponseException $e) {
            $status = $e->getResponse()->getStatusCode();
            $body = (string) $e->getResponse()->getBody();
            $record($status, 'http_error', self::receivedBody($body));

            throw new HttpError($status, self::apiMessage($body));
        } catch (ResponseException $e) {
            // Headers arrived, then the body failed or timed out (or too many redirects), so
            // there is no complete body to keep.
            $timedOut = $e instanceof ResponseTimeoutException;
            $record($e->getResponse()->getStatusCode(), $timedOut ? 'timeout' : 'network');

            throw new NetworkFailure(requestSent: true, timedOut: $timedOut);
        } catch (TransferException) {
            $record(null, 'network');

            throw new NetworkFailure(requestSent: true, timedOut: false);
        }

        $status = $response->getStatusCode();
        $body = (string) $response->getBody();

        try {
            $data = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $record($status, 'bad_response', self::receivedBody($body));

            throw new BadResponse('the body is not JSON.');
        }

        if (!is_array($data)) {
            $record($status, 'bad_response', self::receivedBody($body));

            throw new BadResponse('the body is not a JSON object.');
        }

        $record($status, 'ok', self::receivedBody($body));

        return $data;
    }

    /**
     * Recording is bookkeeping: if it fails, the call's real result must still come back.
     * Losing a created shipment's id because a log insert failed would be far worse.
     */
    private function record(
        ?int $bookingId, string $method, string $endpoint, ?int $status, int $durationMs, string $outcome, ?string $sent, ?string $received,
    ): void {
        try {
            $this->calls->record($bookingId, $method, $endpoint, $status, $durationMs, $outcome, $sent, $received);
        } catch (Throwable $e) {
            $this->logger->warning('Could not record a Shiplogic call', [
                'endpoint' => $endpoint,
                'outcome' => $outcome,
                'exception' => $e::class,
            ]);
        }
    }

    /**
     * What went out: the JSON body, or the query parameters of a GET. Headers (and with them
     * the API key) are never part of it.
     *
     * @param array<string, mixed> $options
     */
    private static function sentBody(array $options): ?string
    {
        $sent = $options[RequestOptions::JSON] ?? $options[RequestOptions::QUERY] ?? null;

        return $sent === null ? null : self::cap(json_encode($sent, self::JSON_FLAGS));
    }

    /**
     * What came back, with the signature part of every URL removed: a signed label link
     * gives anyone the label until it expires. A body that is not JSON is kept as it came.
     */
    private static function receivedBody(string $body): ?string
    {
        if ($body === '') {
            return null;
        }

        try {
            $data = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return self::cap($body);
        }

        return self::cap(json_encode(self::withoutUrlQueries($data), self::JSON_FLAGS));
    }

    private static function withoutUrlQueries(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(self::withoutUrlQueries(...), $value);
        }

        if (is_string($value) && preg_match('#^https?://[^?\s]+\?#i', $value) === 1) {
            return strstr($value, '?', true) . '?[removed]';
        }

        return $value;
    }

    private static function cap(string|false $text): ?string
    {
        if ($text === false) {
            return null;
        }

        return strlen($text) <= self::MAX_LOGGED_BYTES ? $text : mb_strcut($text, 0, self::MAX_LOGGED_BYTES) . '…[cut]';
    }

    /**
     * Shiplogic's error text, kept only to pick a friendly message (see ShiplogicErrors).
     */
    private static function apiMessage(string $body): ?string
    {
        try {
            $data = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        foreach (['message', 'error'] as $key) {
            if (is_array($data) && is_string($data[$key] ?? null)) {
                return mb_substr($data[$key], 0, 500);
            }
        }

        return null;
    }
}
