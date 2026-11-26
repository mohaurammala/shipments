<?php

declare(strict_types=1);

namespace App\Shiplogic;

use App\Shiplogic\Exceptions\BadResponse;
use App\Shiplogic\Exceptions\HttpError;
use App\Shiplogic\Exceptions\NetworkFailure;
use App\Shiplogic\Exceptions\ShiplogicException;

/**
 * Turns a failed call into a message for the user. Shiplogic's own text is matched against
 * the rejections its documentation lists (docs/API-NOTES.md) and is never shown itself.
 */
final class ShiplogicErrors
{
    /**
     * Lower-case fragments of Shiplogic's messages, and what to tell the user instead.
     */
    private const array KNOWN = [
        'maximum weight' => 'A parcel is heavier than this courier accepts.',
        'maximum allowable size' => 'A parcel is larger than this courier accepts.',
        "shouldn't be the same address" => 'The collection and delivery addresses must be different.',
        'mobile number' => 'The courier did not accept a contact mobile number. Check both numbers.',
        'needs a valid email or mobile' => 'The courier did not accept a contact email or mobile number.',
        'postal_code is required' => 'The courier needs a postal code for both addresses.',
        'rate has expired' => 'The quote has expired. Get a new quote, then book again.',
        'declared value' => 'The declared value is more than this courier can cover.',
        'no service levels' => 'This service is no longer available for this shipment. Get a new quote.',
        'no rates for the specified service level' => 'This service is no longer available for this shipment. Get a new quote.',
        'could not find rates' => 'This service is no longer available for this shipment. Get a new quote.',
        'customer reference' => 'The courier requires a customer reference for this route. Contact the administrator.',
        'insufficient funds' => 'The courier account cannot pay for this shipment. Contact the administrator.',
        'credit limit' => 'The courier account cannot pay for this shipment. Contact the administrator.',
    ];

    public static function forUser(ShiplogicException $e, string $fallback): string
    {
        return match (true) {
            $e instanceof NetworkFailure && !$e->requestSent => 'We could not reach the courier. Nothing was sent; try again in a moment.',
            $e instanceof NetworkFailure => 'The courier did not respond in time.',
            $e instanceof BadResponse => 'The courier sent an answer we could not read.',
            $e instanceof HttpError && in_array($e->status, [401, 403], true) => 'The connection to the courier is not authorised. Contact the administrator.',
            $e instanceof HttpError && $e->isRejection() => self::known($e->apiMessage) ?? $fallback,
            default => 'The courier is having problems right now.',
        };
    }

    private static function known(?string $apiMessage): ?string
    {
        $message = mb_strtolower($apiMessage ?? '');

        foreach (self::KNOWN as $fragment => $friendly) {
            if ($message !== '' && str_contains($message, $fragment)) {
                return $friendly;
            }
        }

        return null;
    }
}
