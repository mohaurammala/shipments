<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Domain\Booking;
use App\Domain\Enums\AddressType;
use App\Domain\Enums\BookingStatus;
use App\Domain\ShipmentInput;
use App\Domain\User;
use App\Http\Flash;
use App\Http\HttpException;
use App\Http\Pages;
use App\Http\Request;
use App\Http\Response;
use App\Repositories\BookingRepository;
use App\Repositories\LabelRepository;
use App\Services\Authenticator;
use App\Services\BookingDraftService;
use App\Services\BookingService;
use App\Services\Enums\DraftUpdate;
use App\Services\Enums\QuoteResult;
use App\Services\Enums\SelectResult;
use App\Services\QuoteService;
use App\Shiplogic\ShipmentRequestMapper;
use App\Validation\ShipmentValidator;
use App\Validation\ValidationResult;

/**
 * The bookings list, entering shipment details, getting a quote and choosing a service.
 * Every booking is loaded through BookingRepository::findForUser, so someone else's
 * booking is a 404.
 */
final readonly class BookingController
{
    public function __construct(
        private Pages $pages,
        private BookingRepository $bookings,
        private BookingDraftService $drafts,
        private QuoteService $quotes,
        private BookingService $bookingService,
        private LabelRepository $labels,
        private ShipmentValidator $validator,
        private Authenticator $authenticator,
        private Flash $flash,
        private bool $isTestEnvironment,
    ) {
    }

    public function index(Request $request): Response
    {
        return $this->pages->render('booking/index', [
            'title' => 'Bookings',
            'items' => $this->bookings->listForUser($this->user()),
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->form(null, $this->blankForm());
    }

    public function store(Request $request): Response
    {
        $input = $this->submitted($request);
        $result = $this->validator->validate($input);

        if (!$result->isValid()) {
            return $this->form(null, $input, $result, 422);
        }

        $booking = $this->drafts->create($this->user(), ShipmentInput::fromArray($input));
        $this->flash->success('Draft saved. Check the details below.');

        return Response::redirect("/bookings/{$booking->publicId}");
    }

    /**
     * One page for every step: what it shows follows the booking's status.
     */
    public function show(Request $request): Response
    {
        $booking = $this->booking($request);

        if ($this->bookingService->settleIfStuck($booking)) {
            $booking = $this->booking($request);
        }

        $latest = $this->quotes->latestQuote($booking);
        $current = $this->quotes->currentQuote($booking);

        return $this->pages->render('booking/show', [
            'title' => 'Booking',
            'booking' => $booking,
            'latestQuote' => $latest,
            'quote' => $current,
            'quoteExpired' => $current !== null && $this->quotes->isExpired($current),
            'quoteExpiresAt' => $current === null ? null : $this->quotes->expiresAt($current),
            'selectedRate' => $current !== null && $booking->selectedRateId !== null ? $current->rate($booking->selectedRateId) : null,
            'bookable' => $this->quotes->bookableRate($booking) !== null,
            'labelLastOpenedAt' => $this->labels->lastOpenedAt($booking),
        ]);
    }

    /**
     * POST /rates for this booking's details.
     */
    public function quote(Request $request): Response
    {
        $booking = $this->booking($request);
        $outcome = $this->quotes->quote($booking);

        match ($outcome->result) {
            QuoteResult::Quoted => $this->flash->success('Here are the services available. Choose one to continue.'),
            default => $this->flash->error((string) $outcome->message),
        };

        return Response::redirect("/bookings/{$booking->publicId}");
    }

    /**
     * Keep the chosen rate (its service_level.code is what gets booked).
     */
    public function select(Request $request): Response
    {
        $booking = $this->booking($request);
        $rateId = $request->input('rate_id') ?? '';

        if (!ctype_digit($rateId)) {
            throw HttpException::badRequest();
        }

        match ($this->quotes->select($booking, (int) $rateId)) {
            SelectResult::Selected => $this->flash->success('Service chosen. Check the details, then book.'),
            SelectResult::Expired => $this->flash->error('These prices have expired. Get a new quote.'),
            SelectResult::NotOffered => $this->flash->error('That service is not part of the current quote. Choose from the list below.'),
            SelectResult::Locked => $this->flash->error('The service can no longer be changed for this booking.'),
        };

        return Response::redirect("/bookings/{$booking->publicId}");
    }

    public function edit(Request $request): Response
    {
        $booking = $this->booking($request);

        if (!$booking->status->isEditable()) {
            $this->flash->error('This booking can no longer be changed.');

            return Response::redirect("/bookings/{$booking->publicId}");
        }

        return $this->form($booking, $booking->input->toArray());
    }

    public function update(Request $request): Response
    {
        $booking = $this->booking($request);
        $input = $this->submitted($request);
        $result = $this->validator->validate($input);

        if (!$result->isValid()) {
            return $this->form($booking, $input, $result, 422);
        }

        match ($this->drafts->update($booking, ShipmentInput::fromArray($input))) {
            DraftUpdate::Updated => $this->flash->success(
                $booking->status === BookingStatus::Draft
                    ? 'Details saved.'
                    : 'Details saved. The earlier quote no longer applies.',
            ),
            DraftUpdate::Unchanged => $this->flash->success('Nothing changed.'),
            DraftUpdate::Locked => $this->flash->error('This booking can no longer be changed.'),
        };

        return Response::redirect("/bookings/{$booking->publicId}");
    }

    /**
     * The form's fields, in the shape ShipmentValidator and ShipmentInput expect. Request
     * has already trimmed every value and refused malformed ones (400).
     *
     * @return array<string, mixed>
     */
    private function submitted(Request $request): array
    {
        return [
            'collection' => $request->inputArray('collection'),
            'delivery' => $request->inputArray('delivery'),
            'parcels' => $request->inputArray('parcels'),
            'declared_value' => $request->input('declared_value') ?? '',
            'collection_instructions' => $request->input('collection_instructions') ?? '',
            'delivery_instructions' => $request->input('delivery_instructions') ?? '',
        ];
    }

    /**
     * A new form: one empty parcel, South Africa, and in sandbox mode the test markers the
     * brief requires already in the instructions.
     *
     * @return array<string, mixed>
     */
    private function blankForm(): array
    {
        $address = static fn (AddressType $type): array => ['type' => $type->value, 'country' => 'ZA'];

        return [
            'collection' => ['address' => $address(AddressType::Business), 'contact' => []],
            'delivery' => ['address' => $address(AddressType::Residential), 'contact' => []],
            'parcels' => [[]],
            'declared_value' => '',
            'collection_instructions' => $this->isTestEnvironment ? ShipmentRequestMapper::COLLECT_MARKER : '',
            'delivery_instructions' => $this->isTestEnvironment ? ShipmentRequestMapper::DELIVER_MARKER : '',
        ];
    }

    /**
     * @param array<string, mixed> $values
     */
    private function form(?Booking $booking, array $values, ?ValidationResult $errors = null, int $status = 200): Response
    {
        return $this->pages->render('booking/form', [
            'title' => $booking === null ? 'New booking' : 'Edit booking',
            'booking' => $booking,
            'values' => $values,
            'errors' => $errors ?? new ValidationResult(),
            'maxParcels' => ShipmentValidator::MAX_PARCELS,
        ], $status);
    }

    private function booking(Request $request): Booking
    {
        return $this->bookings->findForUser($request->routeParam('id') ?? '', $this->user())
            ?? throw HttpException::notFound();
    }

    /**
     * RequireLogin has already checked someone is signed in.
     */
    private function user(): User
    {
        return $this->authenticator->user() ?? throw new HttpException(403, 'Sign in to continue.');
    }
}
