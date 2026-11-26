<?php

declare(strict_types=1);

namespace App\Services\Enums;

enum LoginResult
{
    case SignedIn;

    /**
     * Unknown email, wrong password and deactivated account all end here, so the response
     * never reveals which one it was.
     */
    case InvalidCredentials;

    case Throttled;
}
