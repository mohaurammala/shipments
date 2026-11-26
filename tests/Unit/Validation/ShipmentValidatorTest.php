<?php

declare(strict_types=1);

namespace Tests\Unit\Validation;

use App\Domain\ShipmentInput;
use App\Validation\ShipmentValidator;
use App\Validation\ValidationResult;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Support\ShipmentFixture;

final class ShipmentValidatorTest extends TestCase
{
    #[Test]
    public function theFixtureIsAValidShipment(): void
    {
        $result = $this->validate(ShipmentFixture::input());

        self::assertSame([], $result->errors);
    }

    #[Test]
    public function whateverTheValidatorAcceptsCanBecomeAShipment(): void
    {
        $input = ShipmentFixture::input();
        self::assertTrue($this->validate($input)->isValid());

        self::assertSame(1, count(ShipmentInput::fromArray($input)->parcels));
    }

    /**
     * One rule per case: change a single field of a valid shipment, expect an error on
     * exactly that field.
     *
     * @return iterable<string, array{string, mixed}>
     */
    public static function invalidFields(): iterable
    {
        // Addresses
        yield 'no address type' => ['collection.address.type', ''];
        yield 'unknown address type' => ['collection.address.type', 'castle'];
        yield 'business without a company' => ['collection.address.company', ''];
        yield 'no street' => ['delivery.address.street', ''];
        yield 'no suburb' => ['delivery.address.suburb', ''];
        yield 'no city' => ['delivery.address.city', ''];
        yield 'street too long' => ['collection.address.street', str_repeat('a', 255)];
        yield 'unknown province' => ['delivery.address.province', 'XX'];
        yield 'short postcode' => ['collection.address.postcode', '018'];
        yield 'letters in postcode' => ['collection.address.postcode', 'ABCD'];
        yield 'outside South Africa' => ['delivery.address.country', 'NA'];

        // Contacts
        yield 'no contact name' => ['collection.contact.name', ''];
        yield 'no mobile' => ['delivery.contact.mobile', ''];
        yield 'too few digits' => ['delivery.contact.mobile', '082 123 456'];
        yield 'foreign mobile' => ['delivery.contact.mobile', '+44 20 7946 0958'];
        yield 'landline' => ['delivery.contact.mobile', '012 345 6789'];
        yield 'no email' => ['collection.contact.email', ''];
        yield 'not an email' => ['collection.contact.email', 'thandi-at-example'];

        // Parcels: the brief's "non-positive parcel dimensions or weight"
        yield 'zero weight' => ['parcels.0.weight_kg', '0'];
        yield 'negative weight' => ['parcels.0.weight_kg', '-1'];
        yield 'zero length' => ['parcels.0.length_cm', '0.00'];
        yield 'negative width' => ['parcels.0.width_cm', '-30'];
        yield 'missing height' => ['parcels.0.height_cm', ''];
        yield 'weight in words' => ['parcels.0.weight_kg', 'heavy'];
        yield 'scientific notation' => ['parcels.0.length_cm', '1e3'];
        yield 'too long a side' => ['parcels.0.length_cm', '501'];
        yield 'too heavy' => ['parcels.0.weight_kg', '1001'];
        yield 'no description' => ['parcels.0.description', ''];

        // Declared value: the brief's "invalid declared values"
        yield 'no declared value' => ['declared_value', ''];
        yield 'negative declared value' => ['declared_value', '-5'];
        yield 'declared value in words' => ['declared_value', 'lots'];
        yield 'currency symbol' => ['declared_value', 'R1500'];
        yield 'declared value too high' => ['declared_value', '1000000.01'];

        // Instructions
        yield 'instructions too long' => ['delivery_instructions', str_repeat('x', 501)];
    }

    #[Test]
    #[DataProvider('invalidFields')]
    public function eachRuleReportsAgainstItsOwnField(string $path, mixed $value): void
    {
        $result = $this->validate($this->with($path, $value));

        self::assertSame([$path], array_keys($result->errors));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function acceptableValues(): iterable
    {
        yield 'mobile with spaces' => ['collection.contact.mobile', '082 123 4567'];
        yield 'mobile with dashes' => ['collection.contact.mobile', '082-123-4567'];
        yield 'international mobile' => ['collection.contact.mobile', '+27 82 123 4567'];
        yield 'zero declared value' => ['declared_value', '0'];
        yield 'declared value with cents' => ['declared_value', '1500.50'];
        yield 'decimal weight' => ['parcels.0.weight_kg', '0.25'];
        yield 'residential without a company' => ['delivery.address.company', ''];
        yield 'counter address' => ['delivery.address.type', 'counter'];
        yield 'lower-case country' => ['delivery.address.country', 'za'];
        yield 'no instructions' => ['collection_instructions', ''];
    }

    #[Test]
    #[DataProvider('acceptableValues')]
    public function reasonableVariationsAreAccepted(string $path, string $value): void
    {
        self::assertTrue($this->validate($this->with($path, $value))->isValid());
    }

    #[Test]
    public function aShipmentNeedsAtLeastOneParcel(): void
    {
        $input = ShipmentFixture::input();
        $input['parcels'] = [];

        self::assertSame(['parcels'], array_keys($this->validate($input)->errors));
    }

    #[Test]
    public function aShipmentHasAtMostTwentyParcels(): void
    {
        $input = ShipmentFixture::input();
        $input['parcels'] = array_fill(0, ShipmentValidator::MAX_PARCELS + 1, $input['parcels'][0]);

        self::assertSame(['parcels'], array_keys($this->validate($input)->errors));
    }

    #[Test]
    public function errorsKeepTheParcelsOwnKeys(): void
    {
        $input = ShipmentFixture::input();
        $parcel = $input['parcels'][0];
        // The form removed row 1, so the rows left are 0 and 2.
        $input['parcels'] = [0 => $parcel, 2 => [...$parcel, 'weight_kg' => '0']];

        self::assertSame(['parcels.2.weight_kg'], array_keys($this->validate($input)->errors));
    }

    #[Test]
    public function theSameAddressCannotBeBothCollectionAndDelivery(): void
    {
        $input = ShipmentFixture::input();
        $input['delivery']['address'] = [
            ...$input['collection']['address'],
            'street' => '194  BANCOR avenue',
        ];

        self::assertSame(['delivery.address.street'], array_keys($this->validate($input)->errors));
    }

    #[Test]
    public function aMissingSectionIsReportedNotCrashedOn(): void
    {
        $input = ShipmentFixture::input();
        unset($input['delivery']);

        $errors = $this->validate($input)->errors;

        self::assertArrayHasKey('delivery.address.street', $errors);
        self::assertArrayHasKey('delivery.contact.email', $errors);
        self::assertArrayNotHasKey('collection.address.street', $errors);
    }

    #[Test]
    public function aParcelThatIsNotAFormRowIsReported(): void
    {
        $input = ShipmentFixture::input();
        $input['parcels'] = ['not a parcel'];

        self::assertSame(['parcels.0'], array_keys($this->validate($input)->errors));
    }

    /**
     * @param array<array-key, mixed> $input
     */
    private function validate(array $input): ValidationResult
    {
        return (new ShipmentValidator())->validate($input);
    }

    /**
     * The valid fixture with one field, given as "a.b.c", replaced.
     *
     * @return array<array-key, mixed>
     */
    private function with(string $path, mixed $value): array
    {
        $input = ShipmentFixture::input();
        $target = &$input;

        foreach (explode('.', $path) as $key) {
            $target = &$target[$key];
        }

        $target = $value;

        return $input;
    }
}
