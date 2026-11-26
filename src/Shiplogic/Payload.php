<?php

declare(strict_types=1);

namespace App\Shiplogic;

use App\Domain\Address;
use App\Domain\Contact;
use App\Domain\Money;
use App\Domain\Parcel;

/**
 * The pieces both request mappers share: how our field names become Shiplogic's
 * (docs/API-NOTES.md). This and the two mappers are the only code that knows them.
 */
final class Payload
{
    /**
     * @return array<string, string>
     */
    public static function address(Address $address): array
    {
        return [
            'type' => $address->type->value,
            'company' => $address->company ?? '',
            'street_address' => $address->street,
            'local_area' => $address->suburb,
            'city' => $address->city,
            'zone' => $address->province->label(),
            'country' => $address->country,
            'code' => $address->postcode,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function contact(Contact $contact): array
    {
        return ['name' => $contact->name, 'mobile_number' => $contact->mobile, 'email' => $contact->email];
    }

    /**
     * @return array<string, float>
     */
    public static function parcelSize(Parcel $parcel): array
    {
        return [
            'submitted_length_cm' => $parcel->lengthCm,
            'submitted_width_cm' => $parcel->widthCm,
            'submitted_height_cm' => $parcel->heightCm,
            'submitted_weight_kg' => $parcel->weightKg,
        ];
    }

    /**
     * Rand as a number, as in the documentation's examples: 1500, or 1500.5.
     */
    public static function amount(Money $money): int|float
    {
        return $money->cents % 100 === 0 ? intdiv($money->cents, 100) : $money->cents / 100;
    }
}
