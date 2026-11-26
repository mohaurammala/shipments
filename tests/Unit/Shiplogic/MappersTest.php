<?php

declare(strict_types=1);

namespace Tests\Unit\Shiplogic;

use App\Domain\ShipmentInput;
use App\Shiplogic\RatesRequestMapper;
use App\Shiplogic\ShipmentRequestMapper;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Support\ShipmentFixture;

final class MappersTest extends TestCase
{
    #[Test]
    public function aRatesRequestUsesShiplogicsFieldNames(): void
    {
        $body = (new RatesRequestMapper())->map(ShipmentInput::fromArray(ShipmentFixture::input()));

        self::assertSame([
            'type' => 'business',
            'company' => 'Smith & Sons (Pty) Ltd',
            'street_address' => '194 Bancor Avenue',
            'local_area' => 'Menlyn',
            'city' => 'Pretoria',
            'zone' => 'Gauteng',
            'country' => 'ZA',
            'code' => '0181',
        ], $body['collection_address']);
        self::assertSame('', $body['delivery_address']['company']);
        self::assertSame('Western Cape', $body['delivery_address']['zone']);
        self::assertSame([
            'submitted_length_cm' => 40.0,
            'submitted_width_cm' => 30.0,
            'submitted_height_cm' => 20.0,
            'submitted_weight_kg' => 2.5,
        ], $body['parcels'][0]);
        self::assertSame(1500, $body['declared_value']);
        self::assertArrayNotHasKey('collection_contact', $body, 'Rates requests carry no personal details.');
    }

    #[Test]
    public function aShipmentRequestCarriesContactsTheServiceAndTheBookingReference(): void
    {
        $body = $this->shipment(sandbox: false);

        self::assertSame(['name' => 'Thandi Mokoena', 'mobile_number' => '+27821234567', 'email' => 'thandi@example.test'], $body['collection_contact']);
        self::assertSame('Books', $body['parcels'][0]['parcel_description']);
        self::assertSame(2.5, $body['parcels'][0]['submitted_weight_kg']);
        self::assertSame('ECO', $body['service_level_code']);
        self::assertSame('0fb23922-bc4d-43ac-8980-8719e43142ba', $body['customer_reference']);
        self::assertSame('Booking ID', $body['customer_reference_name']);
        self::assertFalse($body['mute_notifications']);
    }

    #[Test]
    public function inSandboxModeEveryShipmentIsMarkedAndSilent(): void
    {
        $input = ShipmentFixture::input();
        $input['collection_instructions'] = 'Gate code 1234';
        $input['delivery_instructions'] = '';

        $body = (new ShipmentRequestMapper(isTestEnvironment: true))->map(ShipmentInput::fromArray($input), 'ECO', 'ref');

        self::assertSame('SANDBOX TEST ONLY - DO NOT COLLECT. Gate code 1234', $body['special_instructions_collection']);
        self::assertSame('SANDBOX TEST ONLY - DO NOT DELIVER', $body['special_instructions_delivery']);
        self::assertTrue($body['mute_notifications']);
    }

    #[Test]
    public function aMarkerAlreadyPresentIsNotRepeated(): void
    {
        $body = $this->shipment(sandbox: true);

        self::assertSame('SANDBOX TEST ONLY - DO NOT COLLECT', $body['special_instructions_collection']);
    }

    #[Test]
    public function amountsWithCentsStayExact(): void
    {
        $input = ShipmentFixture::input();
        $input['declared_value'] = '1500.50';

        self::assertSame(1500.5, (new RatesRequestMapper())->map(ShipmentInput::fromArray($input))['declared_value']);
    }

    /**
     * @return array<string, mixed>
     */
    private function shipment(bool $sandbox): array
    {
        return (new ShipmentRequestMapper($sandbox))->map(
            ShipmentInput::fromArray(ShipmentFixture::input()),
            'ECO',
            '0fb23922-bc4d-43ac-8980-8719e43142ba',
        );
    }
}
