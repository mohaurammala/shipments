<?php

declare(strict_types=1);

namespace App\Validation;

/**
 * The rules for one parcel. The real size and weight limits belong to the courier and are
 * enforced by Shiplogic; the maximums here only catch obvious typos (500 m instead of 500 cm).
 */
final readonly class ParcelValidator
{
    public const int MAX_SIDE_CM = 500;
    public const int MAX_WEIGHT_KG = 1000;
    public const int MAX_DESCRIPTION = 254;

    /**
     * Plain digits with up to two decimals. Unlike is_numeric(), refuses "1e3", " 5" and "0x1A".
     */
    private const string NUMBER = '/^\d{1,4}(?:\.\d{1,2})?$/';

    private const array SIDES = ['length_cm' => 'length', 'width_cm' => 'width', 'height_cm' => 'height'];

    /**
     * @param string $path Where this parcel sits in the form, e.g. "parcels.0".
     * @return array<string, string> Messages keyed by field path; empty when valid.
     */
    public function validate(mixed $parcel, string $path): array
    {
        if (!is_array($parcel)) {
            return [$path => 'This parcel is incomplete. Fill it in or remove it.'];
        }

        $errors = [];
        $description = self::text($parcel['description'] ?? null);

        if ($description === '') {
            $errors["{$path}.description"] = 'Describe what is in the parcel.';
        } elseif (mb_strlen($description) > self::MAX_DESCRIPTION) {
            $errors["{$path}.description"] = 'Keep the description under 255 characters.';
        }

        foreach (self::SIDES as $field => $name) {
            $problem = $this->measurement($parcel[$field] ?? null, $name, 'cm', self::MAX_SIDE_CM);

            if ($problem !== null) {
                $errors["{$path}.{$field}"] = $problem;
            }
        }

        $problem = $this->measurement($parcel['weight_kg'] ?? null, 'weight', 'kg', self::MAX_WEIGHT_KG);

        if ($problem !== null) {
            $errors["{$path}.weight_kg"] = $problem;
        }

        return $errors;
    }

    private function measurement(mixed $value, string $name, string $unit, int $max): ?string
    {
        $text = self::text($value);

        if ($text === '') {
            return "Enter the {$name} in {$unit}.";
        }

        // Checked before the format, so "-5" and "0" get the message that explains the rule.
        if (is_numeric($text) && (float) $text <= 0) {
            return "The {$name} must be more than 0 {$unit}.";
        }

        if (preg_match(self::NUMBER, $text) !== 1) {
            return "Enter the {$name} as a number of {$unit}, like 12.5.";
        }

        if ((float) $text > $max) {
            return "The {$name} cannot be more than {$max} {$unit}.";
        }

        return null;
    }

    private static function text(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }
}
