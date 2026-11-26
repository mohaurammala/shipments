<?php
/**
 * One booking: the step it has reached, the action that comes next, and everything entered.
 * Which sections appear follows the booking's status.
 *
 * @var App\Domain\Booking $booking
 * @var App\Domain\User $currentUser
 * @var App\Domain\Quote|null $latestQuote
 * @var App\Domain\Quote|null $quote
 * @var bool $quoteExpired
 * @var DateTimeImmutable|null $quoteExpiresAt
 * @var App\Domain\Rate|null $selectedRate
 * @var bool $bookable
 * @var DateTimeImmutable|null $labelLastOpenedAt
 * @var string $csrfToken
 */

use App\Domain\Enums\BookingStatus;

$shipment = $booking->input;
$status = $booking->status;
$sides = [
    'Collect from' => [$shipment->collectionAddress, $shipment->collectionContact, $shipment->collectionInstructions],
    'Deliver to' => [$shipment->deliveryAddress, $shipment->deliveryContact, $shipment->deliveryInstructions],
];
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0">Booking</h1>
    <?php if ($status->isEditable()): ?>
        <a class="btn btn-outline-primary btn-sm" href="/bookings/<?= escape($booking->publicId) ?>/edit">Edit details</a>
    <?php endif ?>
</div>
<?php if ($status !== BookingStatus::Cancelled) {
    require __DIR__ . '/../partials/stepper.php';
} ?>

<div class="row g-4">
    <div class="col-lg-8">
        <?php
        // The step this booking is at decides what comes first on the page.
        if ($status === BookingStatus::Created) {
            require __DIR__ . '/_created.php';
        }

        if ($status === BookingStatus::Cancelled) {
            require __DIR__ . '/_cancelled.php';
        }

        if (in_array($status, [BookingStatus::Creating, BookingStatus::OutcomeUnknown], true)) {
            require __DIR__ . '/_pending.php';
        }

        if (in_array($status, [BookingStatus::Selected, BookingStatus::Failed], true)) {
            require __DIR__ . '/_book.php';
        }

        if ($status->isEditable()) {
            require __DIR__ . '/_quote.php';
        }
        ?>

        <?php foreach ($sides as $heading => [$address, $contact, $instructions]): ?>
            <section class="card mb-4">
                <div class="card-body">
                    <h2 class="h5"><?= escape($heading) ?></h2>
                    <div class="row">
                        <div class="col-md-6">
                            <p class="mb-2">
                                <?php if ($address->company !== null): ?><?= escape($address->company) ?><br><?php endif ?>
                                <?= escape($address->street) ?><br>
                                <?= escape($address->suburb) ?>, <?= escape($address->city) ?><br>
                                <?= escape($address->province->label()) ?>, <?= escape($address->postcode) ?>, South Africa
                            </p>
                            <span class="badge text-bg-light border"><?= escape($address->type->label()) ?></span>
                        </div>
                        <div class="col-md-6 mt-3 mt-md-0">
                            <p class="mb-0">
                                <?= escape($contact->name) ?><br>
                                <?= escape($contact->mobile) ?><br>
                                <?= escape($contact->email) ?>
                            </p>
                        </div>
                    </div>
                    <?php if ($instructions !== null): ?>
                        <p class="small text-body-secondary mt-3 mb-0">Instructions: <?= escape($instructions) ?></p>
                    <?php endif ?>
                </div>
            </section>
        <?php endforeach ?>

        <section class="card mb-4">
            <div class="card-body">
                <h2 class="h5">Parcels</h2>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                        <tr>
                            <th scope="col">#</th>
                            <th scope="col">Contents</th>
                            <th scope="col" class="text-end">L × W × H (cm)</th>
                            <th scope="col" class="text-end">Weight (kg)</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($shipment->parcels as $index => $parcel): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td><?= escape($parcel->description) ?></td>
                                <td class="text-end"><?= escape("{$parcel->lengthCm} × {$parcel->widthCm} × {$parcel->heightCm}") ?></td>
                                <td class="text-end"><?= escape((string) $parcel->weightKg) ?></td>
                            </tr>
                        <?php endforeach ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>

    <div class="col-lg-4">
        <?php require __DIR__ . '/../partials/summary.php'; ?>
    </div>
</div>
