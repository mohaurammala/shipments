<?php

declare(strict_types=1);

namespace App\Validation;

use App\Domain\Contact;
use App\Domain\Enums\AddressType;
use App\Domain\Enums\Province;
use App\Domain\Money;

/**
 * Every rule for a shipment, from the brief and from Shiplogic's own rejections. Errors are
 * keyed by the form field's path ("collection.address.postcode", "parcels.1.weight_kg"), so
 * the form can show each message next to its field. Anything accepted here can be turned
 * into a ShipmentInput.
 */
final readonly class ShipmentValidator
{
    public const int MAX_PARCELS = 20;
    public const int MAX_TEXT = 254;
    public const int MAX_INSTRUCTIONS = 500;
    public const int MAX_DECLARED_VALUE_CENTS = 100_000_000;

    private const string POSTCODE = '/^\d{4}$/';
    private const string MOBILE = '/^\+27[6-8]\d{8}$/';

    public function __construct(private ParcelValidator $parcels = new ParcelValidator())
    {
    }

    /**
     * @param array<array-key, mixed> $input The form's shape (see ShipmentInput::toArray).
     */
    public function validate(array $input): ValidationResult
    {
        $errors = [
            ...$this->address($input, 'collection'),
            ...$this->contact($input, 'collection'),
            ...$this->address($input, 'delivery'),
            ...$this->contact($input, 'delivery'),
            ...$this->parcelList($input),
            ...$this->declaredValue($input),
            ...$this->instructions($input),
        ];

        // Shiplogic refuses a shipment whose two addresses are the same.
        if (!isset($errors['delivery.address.street']) && $this->addressesMatch($input)) {
            $errors['delivery.address.street'] = 'The delivery address must be different from the collection address.';
        }

        return new ValidationResult($errors);
    }

    /**
     * @param array<array-key, mixed> $input
     * @return array<string, string>
     */
    private function address(array $input, string $side): array
    {
        $path = "{$side}.address";
        $errors = [];
        $type = AddressType::tryFrom($this->text($input, "{$path}.type"));
        $company = $this->text($input, "{$path}.company");

        if ($type === null) {
            $errors["{$path}.type"] = 'Choose an address type.';
        }

        if ($type === AddressType::Business && $company === '') {
            $errors["{$path}.company"] = 'Enter the company name for a business address.';
        } elseif (mb_strlen($company) > self::MAX_TEXT) {
            $errors["{$path}.company"] = 'Keep the company name under 255 characters.';
        }

        foreach (['street' => 'the street address', 'suburb' => 'the suburb', 'city' => 'the city or town'] as $field => $label) {
            $value = $this->text($input, "{$path}.{$field}");

            if ($value === '') {
                $errors["{$path}.{$field}"] = "Enter {$label}.";
            } elseif (mb_strlen($value) > self::MAX_TEXT) {
                $errors["{$path}.{$field}"] = "Keep {$label} under 255 characters.";
            }
        }

        if (Province::tryFrom($this->text($input, "{$path}.province")) === null) {
            $errors["{$path}.province"] = 'Choose a province.';
        }

        if (preg_match(self::POSTCODE, $this->text($input, "{$path}.postcode")) !== 1) {
            $errors["{$path}.postcode"] = 'Enter the 4-digit postal code.';
        }

        if (strtoupper($this->text($input, "{$path}.country")) !== 'ZA') {
            $errors["{$path}.country"] = 'Only addresses in South Africa can be used.';
        }

        return $errors;
    }

    /**
     * @param array<array-key, mixed> $input
     * @return array<string, string>
     */
    private function contact(array $input, string $side): array
    {
        $path = "{$side}.contact";
        $errors = [];
        $name = $this->text($input, "{$path}.name");
        $mobile = $this->text($input, "{$path}.mobile");
        $email = $this->text($input, "{$path}.email");

        if ($name === '') {
            $errors["{$path}.name"] = "Enter the contact person's name.";
        } elseif (mb_strlen($name) > self::MAX_TEXT) {
            $errors["{$path}.name"] = 'Keep the name under 255 characters.';
        }

        // Checked after normalising, exactly as Contact stores it, so the two always agree.
        if ($mobile === '') {
            $errors["{$path}.mobile"] = 'Enter a mobile number.';
        } elseif (preg_match(self::MOBILE, Contact::normaliseMobile($mobile)) !== 1) {
            $errors["{$path}.mobile"] = 'Enter a South African mobile number, like 082 123 4567.';
        }

        if ($email === '') {
            $errors["{$path}.email"] = 'Enter an email address.';
        } elseif (strlen($email) > self::MAX_TEXT || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors["{$path}.email"] = 'Enter a valid email address, like name@example.com.';
        }

        return $errors;
    }

    /**
     * @param array<array-key, mixed> $input
     * @return array<string, string>
     */
    private function parcelList(array $input): array
    {
        $parcels = $input['parcels'] ?? null;

        if (!is_array($parcels) || $parcels === []) {
            return ['parcels' => 'Add at least one parcel.'];
        }

        if (count($parcels) > self::MAX_PARCELS) {
            return ['parcels' => sprintf('A shipment can have at most %d parcels.', self::MAX_PARCELS)];
        }

        $errors = [];

        // Keyed by each parcel's own key, so a removed row in the middle of the form does not
        // shift the messages onto the wrong parcel.
        foreach ($parcels as $key => $parcel) {
            $errors = [...$errors, ...$this->parcels->validate($parcel, "parcels.{$key}")];
        }

        return $errors;
    }

    /**
     * @param array<array-key, mixed> $input
     * @return array<string, string>
     */
    private function declaredValue(array $input): array
    {
        $value = $this->text($input, 'declared_value');

        if ($value === '') {
            return ['declared_value' => 'Enter the value of the contents in rand (0 if none).'];
        }

        if (!Money::isDecimal($value)) {
            return ['declared_value' => 'Enter the declared value in rand, like 1500 or 1500.00.'];
        }

        if (Money::fromDecimal($value)->cents > self::MAX_DECLARED_VALUE_CENTS) {
            return ['declared_value' => 'Declared values above R1 000 000 are not supported.'];
        }

        return [];
    }

    /**
     * @param array<array-key, mixed> $input
     * @return array<string, string>
     */
    private function instructions(array $input): array
    {
        $errors = [];

        foreach (['collection_instructions', 'delivery_instructions'] as $field) {
            if (mb_strlen($this->text($input, $field)) > self::MAX_INSTRUCTIONS) {
                $errors[$field] = sprintf('Keep the instructions under %d characters.', self::MAX_INSTRUCTIONS);
            }
        }

        return $errors;
    }

    /**
     * Compared case-insensitively and ignoring extra spaces: "12 Long St" and "12  long st"
     * are the same place.
     *
     * @param array<array-key, mixed> $input
     */
    private function addressesMatch(array $input): bool
    {
        $key = function (string $side) use ($input): string {
            $parts = array_map(
                fn (string $field): string => $this->text($input, "{$side}.address.{$field}"),
                ['street', 'suburb', 'city', 'postcode'],
            );

            return mb_strtolower(preg_replace('/\s+/', ' ', implode('|', $parts)) ?? '');
        };

        return $key('collection') !== '|||' && $key('collection') === $key('delivery');
    }

    /**
     * The value at a dotted path ("collection.address.city"), or "" if it is missing or not
     * text. The validator never assumes the form was complete.
     *
     * @param array<array-key, mixed> $input
     */
    private function text(array $input, string $path): string
    {
        $value = $input;

        foreach (explode('.', $path) as $key) {
            if (!is_array($value) || !array_key_exists($key, $value)) {
                return '';
            }

            $value = $value[$key];
        }

        return is_string($value) ? trim($value) : '';
    }
}
