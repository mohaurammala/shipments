<?php

declare(strict_types=1);

namespace App\Domain;

use InvalidArgumentException;

/**
 * An amount in rand, held as whole cents. Never a float: 0.1 + 0.2 is not 0.3 in floating
 * point, and money must add up exactly.
 */
final readonly class Money
{
    private const string DECIMAL = '/^(\d{1,10})(?:\.(\d{1,2}))?$/';

    private function __construct(public int $cents)
    {
    }

    public static function fromCents(int $cents): self
    {
        if ($cents < 0) {
            throw new InvalidArgumentException('An amount cannot be negative.');
        }

        return new self($cents);
    }

    /**
     * "1500", "1500.5" and "1500.50" all mean R1 500.50 or R1 500.00 exactly. Parsed as
     * text, so no floating-point rounding is ever involved.
     */
    public static function fromDecimal(string $amount): self
    {
        if (preg_match(self::DECIMAL, $amount, $parts) !== 1) {
            throw new InvalidArgumentException('An amount must look like 1500 or 1500.00.');
        }

        $rands = (int) $parts[1];
        $cents = (int) str_pad($parts[2] ?? '', 2, '0');

        return new self($rands * 100 + $cents);
    }

    public static function isDecimal(string $amount): bool
    {
        return preg_match(self::DECIMAL, $amount) === 1;
    }

    /**
     * "1500.00": the form and the API both use this.
     */
    public function toDecimal(): string
    {
        return sprintf('%d.%02d', intdiv($this->cents, 100), $this->cents % 100);
    }
}
