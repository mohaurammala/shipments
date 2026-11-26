<?php


declare(strict_types=1);

namespace Tests\Support;

/**
 * A valid shipment in the form's shape, for tests to start from and change one thing.
 */
final class ShipmentFixture
{
    /**
     * @return array<string, mixed>
     */
    public static function input(): array
    {
        return [
            'collection' => [
                'address' => [
                    'type' => 'business',
                    'company' => 'Smith & Sons (Pty) Ltd',
                    'street' => '194 Bancor Avenue',
                    'suburb' => 'Menlyn',
                    'city' => 'Pretoria',
                    'province' => 'GP',
                    'postcode' => '0181',
                    'country' => 'ZA',
                ],
                'contact' => ['name' => 'Thandi Mokoena', 'mobile' => '082 123 4567', 'email' => 'Thandi@Example.test'],
            ],
            'delivery' => [
                'address' => [
                    'type' => 'residential',
                    'company' => '',
                    'street' => '12 Long Street',
                    'suburb' => 'City Centre',
                    'city' => 'Cape Town',
                    'province' => 'WC',
                    'postcode' => '8001',
                    'country' => 'ZA',
                ],
                'contact' => ['name' => "Sipho O'Brien", 'mobile' => '+27 71 234 5678', 'email' => 'sipho@example.test'],
            ],
            'parcels' => [
                ['description' => 'Books', 'length_cm' => '40', 'width_cm' => '30', 'height_cm' => '20', 'weight_kg' => '2.5'],
            ],
            'declared_value' => '1500',
            'collection_instructions' => 'SANDBOX TEST ONLY - DO NOT COLLECT',
            'delivery_instructions' => '',
        ];
    }
}
