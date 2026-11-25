<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Random (version 4) UUIDs for public identifiers such as a booking's URL. Unlike an
 * auto-increment id, one cannot be guessed from another.
 */
final class Uuid
{
    public static function v4(): string
    {
        $bytes = random_bytes(16);

        // Mark the value as version 4 (random) and RFC 9562 variant, as the format requires.
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
