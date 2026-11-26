<?php

declare(strict_types=1);

namespace Tests\Unit\Shiplogic;

use App\Shiplogic\CancelledShipment;
use App\Shiplogic\CreatedShipment;
use App\Shiplogic\Exceptions\BadResponse;
use App\Shiplogic\Exceptions\HttpError;
use App\Shiplogic\Exceptions\NetworkFailure;
use App\Shiplogic\RatesResponse;
use App\Shiplogic\ShiplogicErrors;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Support\ShiplogicFixture;

final class ResponsesTest extends TestCase
{
    #[Test]
    public function ratesArePricedInWholeCentsWithTheirVatAndDeliveryWindow(): void
    {
        $rate = RatesResponse::fromArray(ShiplogicFixture::rates())->rates[0];

        self::assertSame('LOF', $rate->serviceLevelCode);
        self::assertSame(212573, $rate->serviceLevelId);
        self::assertSame(11625, $rate->total->cents);
        self::assertSame(10109, $rate->totalExcludingVat?->cents, '101.0869… rounds to R101.09');
        self::assertSame(1516, $rate->vat()?->cents);
        self::assertSame('standard', $rate->vatType);
        self::assertSame(15.0, $rate->vatPercentage);
        self::assertSame('2026-11-26 14:00', $rate->deliveryFrom?->format('Y-m-d H:i'), 'stored in UTC');
    }

    #[Test]
    public function noRatesIsAnEmptyListNotAnError(): void
    {
        self::assertSame([], RatesResponse::fromArray(['message' => 'Success', 'rates' => []])->rates);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function unusableRates(): iterable
    {
        $rate = ShiplogicFixture::rates()['rates'][0];

        yield 'no rates list' => [['message' => 'Success']];
        yield 'no service level code' => [['rates' => [[...$rate, 'service_level' => [...$rate['service_level'], 'code' => '']]]]];
        yield 'no price' => [['rates' => [[...$rate, 'rate' => '116.25']]]];
        yield 'negative price' => [['rates' => [[...$rate, 'rate' => -1]]]];
    }

    /**
     * @param array<string, mixed> $data
     */
    #[Test]
    #[DataProvider('unusableRates')]
    public function aRateWeCannotPriceOrBookIsRefused(array $data): void
    {
        $this->expectException(BadResponse::class);

        RatesResponse::fromArray($data);
    }

    #[Test]
    public function aCreatedShipmentNeedsAnId(): void
    {
        $created = CreatedShipment::fromArray(ShiplogicFixture::createdShipment(14135));
        self::assertSame('14135', $created->id);
        self::assertSame('G9GXYZ', $created->trackingReference);

        $this->expectException(BadResponse::class);
        CreatedShipment::fromArray(['status' => 'submitted']);
    }

    #[Test]
    public function aCancellationIsReadFromTheShipmentsStatus(): void
    {
        $cancelled = CancelledShipment::fromArray(ShiplogicFixture::cancelledShipment(14135));

        self::assertSame('14135', $cancelled->id);
        self::assertSame('cancelled', $cancelled->status);
    }

    #[Test]
    public function aSuccessThatDoesNotSayCancelledIsNotTrusted(): void
    {
        $this->expectException(BadResponse::class);

        CancelledShipment::fromArray(['id' => 14135, 'status' => 'collected']);
    }

    /**
     * @return iterable<string, array{\App\Shiplogic\Exceptions\ShiplogicException, string}>
     */
    public static function failures(): iterable
    {
        yield 'too heavy' => [new HttpError(400, 'parcel exceeds the maximum weight of 30 kg'), 'heavier'];
        yield 'same address' => [new HttpError(400, "the collection address and delivery address shouldn't be the same address"), 'must be different'];
        yield 'rate expired' => [new HttpError(400, 'rate has expired – please try again'), 'expired'];
        yield 'unknown rejection' => [new HttpError(400, 'something new'), 'FALLBACK'];
        yield 'bad API key' => [new HttpError(401, null), 'not authorised'];
        yield 'server error' => [new HttpError(500, 'could not create shipment'), 'having problems'];
        yield 'never connected' => [new NetworkFailure(requestSent: false, timedOut: false), 'Nothing was sent'];
        yield 'timed out' => [new NetworkFailure(requestSent: true, timedOut: true), 'did not respond'];
    }

    #[Test]
    #[DataProvider('failures')]
    public function failuresBecomeFriendlyMessagesWithoutShiplogicsOwnText(\App\Shiplogic\Exceptions\ShiplogicException $e, string $expected): void
    {
        $message = ShiplogicErrors::forUser($e, 'FALLBACK');

        self::assertStringContainsString($expected, $message);

        if ($e instanceof HttpError && $e->apiMessage !== null) {
            self::assertStringNotContainsString($e->apiMessage, $message);
        }
    }
}
