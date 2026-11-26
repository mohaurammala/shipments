<?php

declare(strict_types=1);

namespace App\Domain\Enums;

/**
 * "Zones" for address payloads
 */
enum Province: string
{
    case EasternCape = 'EC';
    case FreeState = 'FS';
    case Gauteng = 'GP';
    case KwaZuluNatal = 'KZN';
    case Limpopo = 'LP';
    case Mpumalanga = 'MP';
    case NorthernCape = 'NC';
    case NorthWest = 'NW';
    case WesternCape = 'WC';

    public function label(): string
    {
        return match ($this) {
            self::EasternCape => 'Eastern Cape',
            self::FreeState => 'Free State',
            self::Gauteng => 'Gauteng',
            self::KwaZuluNatal => 'KwaZulu-Natal',
            self::Limpopo => 'Limpopo',
            self::Mpumalanga => 'Mpumalanga',
            self::NorthernCape => 'Northern Cape',
            self::NorthWest => 'North West',
            self::WesternCape => 'Western Cape',
        };
    }
}
