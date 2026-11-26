<?php

declare(strict_types=1);

namespace App\Domain\Enums;

/**
 * Values match the users.role CHECK constraint.
 */
enum Role: string
{
    case Admin = 'admin';
    case Operator = 'operator';
}
