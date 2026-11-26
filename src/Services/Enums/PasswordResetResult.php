<?php

declare(strict_types=1);

namespace App\Services\Enums;

enum PasswordResetResult
{
    case Reset;

    case InvalidKey;

    case Throttled;

    /**
     * Only reachable with the correct key, so it reveals nothing to an outsider.
     */
    case UnknownAccount;

    case WeakPassword;
}
