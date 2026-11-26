<?php

declare(strict_types=1);

namespace App\Domain;

use InvalidArgumentException;

/**
 * Everything the user entered for one shipment: what gets quoted, stored as the booking's
 * input_snapshot, and sent to Shiplogic.
 */
final readonly class ShipmentInput
{
    /**
     * @param list<Parcel> $parcels At least one.
     */
    public function __construct(
        public Address $collectionAddress,
        public Contact $collectionContact,
        public Address $deliveryAddress,
        public Contact $deliveryContact,
        public array $parcels,
        public Money $declaredValue,
        public ?string $collectionInstructions,
        public ?string $deliveryInstructions,
    ) {
        if ($parcels === []) {
            throw new InvalidArgumentException('A shipment needs at least one parcel.');
        }
    }

    /**
     * Builds the shipment from the form's shape (see toArray). Call it only after
     * ShipmentValidator has accepted the same array.
     *
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            collectionAddress: Address::fromArray($data['collection']['address']),
            collectionContact: Contact::fromArray($data['collection']['contact']),
            deliveryAddress: Address::fromArray($data['delivery']['address']),
            deliveryContact: Contact::fromArray($data['delivery']['contact']),
            parcels: array_map(Parcel::fromArray(...), array_values($data['parcels'])),
            declaredValue: Money::fromDecimal((string) $data['declared_value']),
            collectionInstructions: self::optional($data['collection_instructions'] ?? ''),
            deliveryInstructions: self::optional($data['delivery_instructions'] ?? ''),
        );
    }

    /**
     * The same nested shape and field names as the form, with values normalised:
     * fromArray(toArray()) gives back an identical shipment.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'collection' => [
                'address' => $this->collectionAddress->toArray(),
                'contact' => $this->collectionContact->toArray(),
            ],
            'delivery' => [
                'address' => $this->deliveryAddress->toArray(),
                'contact' => $this->deliveryContact->toArray(),
            ],
            'parcels' => array_map(static fn (Parcel $parcel): array => $parcel->toArray(), $this->parcels),
            'declared_value' => $this->declaredValue->toDecimal(),
            'collection_instructions' => $this->collectionInstructions ?? '',
            'delivery_instructions' => $this->deliveryInstructions ?? '',
        ];
    }

    /**
     * A quote is only valid for the exact input it was made for:
     * if this changes, the selection is cleared and a new quote is needed.
     * Keys are sorted so their order never matters; the parcel list keeps its order.
     */
    public function hash(): string
    {
        $json = json_encode(
            self::sortKeys($this->toArray()),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR,
        );

        return hash('sha256', $json);
    }

    private static function optional(mixed $value): ?string
    {
        $text = (string) $value;

        return $text === '' ? null : $text;
    }

    /**
     * @param array<array-key, mixed> $value
     * @return array<array-key, mixed>
     */
    private static function sortKeys(array $value): array
    {
        if (!array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = self::sortKeys($item);
            }
        }

        return $value;
    }
}
