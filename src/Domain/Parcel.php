<?php


declare(strict_types=1);

namespace App\Domain;

/**
 * One parcel in a shipment. Sizes in centimetres, weight in kilograms.
 */
final readonly class Parcel
{
    public function __construct(
        public string $description,
        public float  $lengthCm,
        public float  $widthCm,
        public float  $heightCm,
        public float  $weightKg,
    )
    {}

    /**
     * @param array<array-key, mixed> $data Input ShipmentValidator has accepted.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            description: (string)$data['description'],
            lengthCm: (float)$data['length_cm'],
            widthCm: (float)$data['width_cm'],
            heightCm: (float)$data['height_cm'],
            weightKg: (float)$data['weight_kg'],
        );
    }

    /**
     * @return array{description: string, length_cm: float, width_cm: float, height_cm: float, weight_kg: float}
     */
    public function toArray(): array
    {
        return [
            'description' => $this->description,
            'length_cm' => $this->lengthCm,
            'width_cm' => $this->widthCm,
            'height_cm' => $this->heightCm,
            'weight_kg' => $this->weightKg,
        ];
    }
}
