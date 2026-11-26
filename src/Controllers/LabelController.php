<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Domain\User;
use App\Http\Flash;
use App\Http\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Repositories\BookingRepository;
use App\Services\Authenticator;
use App\Services\LabelService;

/**
 * "Open label" is a link (GET), opened in a new tab. It fetches a fresh signed
 * link for the existing shipment and redirects to the PDF, where the browser's own viewer
 * prints it.
 *
 * A GET is right here because nothing about the booking changes (the fetch is only
 * recorded), so it needs no CSRF token: another site could make a browser load it but
 * could not read the result. It also avoids the CSP form-action rule, which would block a
 * form whose redirect leaves this site.
 */
final readonly class LabelController
{
    /**
     * Only Shiplogic's label host is ever a redirect target.
     */
    private const array LABEL_HOSTS = ['.shiplogic.com'];

    public function __construct(
        private BookingRepository $bookings,
        private LabelService $labels,
        private Authenticator $authenticator,
        private Flash $flash,
    ) {
    }

    public function open(Request $request): Response
    {
        $user = $this->user();
        $booking = $this->bookings->findForUser($request->routeParam('id') ?? '', $user) ?? throw HttpException::notFound();
        $outcome = $this->labels->fetch($booking, $user);

        if ($outcome->url === null) {
            // Same page, same link: trying again only ever asks for this shipment's label.
            $this->flash->error((string) $outcome->message);

            return Response::redirect("/bookings/{$booking->publicId}");
        }

        return Response::redirectToTrustedHost($outcome->url, self::LABEL_HOSTS);
    }

    private function user(): User
    {
        return $this->authenticator->user() ?? throw new HttpException(403, 'Sign in to continue.');
    }
}
