<?php

declare(strict_types=1);

namespace App\Domain\Enums;

enum AddressType: string
{
    case Business = 'business';
    case Residential = 'residential';
    case Counter = 'counter';
    case Locker = 'locker';

    public function label(): string
    {
        return match ($this) {
            self::Business => 'Business',
            self::Residential => 'Residential',
            self::Counter => 'Counter',
            self::Locker => 'Locker',
        };
    }
}
