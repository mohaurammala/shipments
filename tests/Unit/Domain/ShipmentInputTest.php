<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\Enums\AddressType;
use App\Domain\Enums\Province;
use App\Domain\ShipmentInput;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Support\ShipmentFixture;

final class ShipmentInputTest extends TestCase
{
    #[Test]
    public function theFormInputBecomesTypedValues(): void
    {
        $shipment = ShipmentInput::fromArray(ShipmentFixture::input());

        self::assertSame(AddressType::Business, $shipment->collectionAddress->type);
        self::assertSame(Province::WesternCape, $shipment->deliveryAddress->province);
        self::assertNull($shipment->deliveryAddress->company);
        self::assertSame(2.5, $shipment->parcels[0]->weightKg);
        self::assertSame(150000, $shipment->declaredValue->cents);
        self::assertNull($shipment->deliveryInstructions);
    }

    #[Test]
    public function contactsAreNormalised(): void
    {
        $shipment = ShipmentInput::fromArray(ShipmentFixture::input());

        self::assertSame('+27821234567', $shipment->collectionContact->mobile);
        self::assertSame('+27712345678', $shipment->deliveryContact->mobile);
        self::assertSame('thandi@example.test', $shipment->collectionContact->email);
    }

    #[Test]
    public function textIsKeptExactlyAsTyped(): void
    {
        $data = ShipmentInput::fromArray(ShipmentFixture::input())->toArray();

        self::assertSame('Smith & Sons (Pty) Ltd', $data['collection']['address']['company']);
        self::assertSame("Sipho O'Brien", $data['delivery']['contact']['name']);
    }

    #[Test]
    public function readingBackWhatWasWrittenGivesTheSameShipment(): void
    {
        $shipment = ShipmentInput::fromArray(ShipmentFixture::input());

        $again = ShipmentInput::fromArray($shipment->toArray());

        self::assertEquals($shipment, $again);
        self::assertSame($shipment->hash(), $again->hash());
    }

    #[Test]
    public function theOrderOfFieldsDoesNotChangeTheHash(): void
    {
        $input = ShipmentFixture::input();
        $reordered = array_reverse($input, true);
        $reordered['collection']['address'] = array_reverse($input['collection']['address'], true);

        self::assertSame(
            ShipmentInput::fromArray($input)->hash(),
            ShipmentInput::fromArray($reordered)->hash(),
        );
    }

    #[Test]
    public function differentlyFormattedButEqualValuesGiveTheSameHash(): void
    {
        $original = ShipmentFixture::input();
        $reformatted = $original;
        $reformatted['collection']['contact']['mobile'] = '+27 82-123-4567';
        $reformatted['collection']['contact']['email'] = 'THANDI@example.test';
        $reformatted['parcels'][0]['weight_kg'] = '2.50';
        $reformatted['declared_value'] = '1500.00';

        self::assertSame(ShipmentInput::fromArray($original)->hash(), ShipmentInput::fromArray($reformatted)->hash());
    }

    #[Test]
    public function anyRealChangeGivesADifferentHash(): void
    {
        $original = ShipmentFixture::input();
        $heavier = $original;
        $heavier['parcels'][0]['weight_kg'] = '2.6';

        self::assertNotSame(ShipmentInput::fromArray($original)->hash(), ShipmentInput::fromArray($heavier)->hash());
    }

    #[Test]
    public function aShipmentWithoutParcelsCannotExist(): void
    {
        $input = ShipmentFixture::input();
        $input['parcels'] = [];

        $this->expectException(InvalidArgumentException::class);

        ShipmentInput::fromArray($input);
    }
}
