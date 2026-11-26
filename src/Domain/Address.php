<?php

declare(strict_types=1);

namespace App\Domain;

use App\Domain\Enums\AddressType;
use App\Domain\Enums\Province;

/**
 * A collection or delivery address. All four Shiplogic address types (business,
 * residential, counter, locker) use the same fields.
 */
final readonly class Address
{
    public function __construct(
        public AddressType $type,
        public ?string $company,
        public string $street,
        public string $suburb,
        public string $city,
        public Province $province,
        public string $postcode,
        public string $country,
    ) {
    }

    /**
     * Expects input that ShipmentValidator has accepted. Enum::from() still throws on an
     * unknown type or province, as a last line of defence.
     *
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $company = (string) ($data['company'] ?? '');

        return new self(
            type: AddressType::from((string) $data['type']),
            company: $company === '' ? null : $company,
            street: (string) $data['street'],
            suburb: (string) $data['suburb'],
            city: (string) $data['city'],
            province: Province::from((string) $data['province']),
            postcode: (string) $data['postcode'],
            country: strtoupper((string) $data['country']),
        );
    }

    /**
     * The same shape and field names as the form, so a draft can be shown again for editing.
     *
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'company' => $this->company ?? '',
            'street' => $this->street,
            'suburb' => $this->suburb,
            'city' => $this->city,
            'province' => $this->province->value,
            'postcode' => $this->postcode,
            'country' => $this->country,
        ];
    }
}
