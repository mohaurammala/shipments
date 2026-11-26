<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Domain\Booking;
use App\Domain\User;
use App\Http\Flash;
use App\Http\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Repositories\BookingRepository;
use App\Services\Authenticator;
use App\Services\BookingService;
use App\Services\Enums\BookResult;

/**
 * Booking the shipment, cancelling it, and an admin settling a booking whose outcome is unknown.
 */
final readonly class ShipmentController
{
    private const string SHIPMENT_ID = '/^[A-Za-z0-9-]{1,64}$/';

    public function __construct(
        private BookingRepository $bookings,
        private BookingService $service,
        private Authenticator $authenticator,
        private Flash $flash,
    ) {
    }

    public function book(Request $request): Response
    {
        $booking = $this->booking($request);
        $outcome = $this->service->book($booking);

        $outcome->result === BookResult::Booked
            ? $this->flash->success($outcome->message)
            : $this->flash->error($outcome->message);

        return Response::redirect("/bookings/{$booking->publicId}");
    }

    public function cancel(Request $request): Response
    {
        $booking = $this->booking($request);
        $outcome = $this->service->cancel($booking);

        $outcome->result === BookResult::Cancelled
            ? $this->flash->success($outcome->message)
            : $this->flash->error($outcome->message);

        return Response::redirect("/bookings/{$booking->publicId}");
    }

    /**
     * Admins only (see routes.php).
     */
    public function resolve(Request $request): Response
    {
        $booking = $this->booking($request);
        $outcome = $request->input('outcome') ?? '';
        $shipmentId = $request->input('shipment_id') ?? '';
        $tracking = $request->input('tracking_reference') ?? '';

        if ($outcome === 'booked' && preg_match(self::SHIPMENT_ID, $shipmentId) !== 1) {
            $this->flash->error('Enter the shipment id shown in the Shiplogic portal (letters, digits and dashes).');

            return Response::redirect("/bookings/{$booking->publicId}");
        }

        if (!in_array($outcome, ['booked', 'not_booked'], true) || mb_strlen($tracking) > 64) {
            throw HttpException::badRequest();
        }

        $result = $this->service->resolve(
            $booking,
            $this->user(),
            $outcome === 'booked' ? $shipmentId : null,
            $outcome === 'booked' && $tracking !== '' ? $tracking : null,
        );

        in_array($result->result, [BookResult::Booked, BookResult::Failed], true)
            ? $this->flash->success($result->message)
            : $this->flash->error($result->message);

        return Response::redirect("/bookings/{$booking->publicId}");
    }

    private function booking(Request $request): Booking
    {
        return $this->bookings->findForUser($request->routeParam('id') ?? '', $this->user())
            ?? throw HttpException::notFound();
    }

    private function user(): User
    {
        return $this->authenticator->user() ?? throw new HttpException(403, 'Sign in to continue.');
    }
}
