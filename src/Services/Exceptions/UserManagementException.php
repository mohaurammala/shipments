<?php

declare(strict_types=1);

namespace App\Services\Exceptions;

use DomainException;

/**
 * A user-management rule said no. The message is written for the admin and safe to show.
 */
final class UserManagementException extends DomainException
{
}
