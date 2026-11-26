<?php
/**
 * Review the chosen service and book. Included by show.php for SELECTED and
 * FAILED bookings.
 *
 * @var App\Domain\Booking $booking
 * @var App\Domain\Rate|null $selectedRate
 * @var bool $bookable The quote still applies and has not expired.
 * @var string $csrfToken
 */

use App\Domain\Enums\BookingStatus;

$failed = $booking->status === BookingStatus::Failed;
?>
<section class="card mb-4 border-primary" id="book">
    <div class="card-body">
        <h2 class="h5"><?= $failed ? 'Booking not made' : 'Review and book' ?></h2>

        <?php if ($failed && $booking->failureReason !== null): ?>
            <div class="alert alert-danger"><?= escape($booking->failureReason) ?></div>
        <?php endif ?>

        <?php if ($selectedRate !== null): ?>
            <dl class="row mb-3">
                <dt class="col-sm-4">Service</dt>
                <dd class="col-sm-8"><?= escape($selectedRate->serviceName) ?> (<?= escape($selectedRate->serviceLevelCode) ?>)</dd>
                <dt class="col-sm-4">Total</dt>
                <dd class="col-sm-8">
                    <?= escape(rand_amount($selectedRate->total)) ?> including VAT
                    <?php if ($selectedRate->vat() !== null): ?>
                        <span class="text-body-secondary small">(VAT <?= escape(rand_amount($selectedRate->vat())) ?>)</span>
                    <?php endif ?>
                </dd>
                <?php if ($selectedRate->deliveryFrom !== null): ?>
                    <dt class="col-sm-4">Estimated delivery</dt>
                    <dd class="col-sm-8"><?= escape(local_time($selectedRate->deliveryFrom)) ?><?php if ($selectedRate->deliveryTo !== null): ?> – <?= escape(local_time($selectedRate->deliveryTo)) ?><?php endif ?></dd>
                <?php endif ?>
            </dl>
        <?php endif ?>

        <?php if ($bookable): ?>
            <p class="small text-body-secondary">
                Booking sends this shipment to the courier and cannot be undone here. The details can no longer be edited afterwards.
            </p>
            <form method="post" action="/bookings/<?= escape($booking->publicId) ?>/book" data-lock-on-submit>
                <input type="hidden" name="_token" value="<?= escape($csrfToken) ?>">
                <button type="submit" class="btn btn-primary"><?= $failed ? 'Try booking again' : 'Book shipment' ?></button>
            </form>
        <?php else: ?>
            <div class="alert alert-warning mb-0">The quote has expired or no longer matches the details. Get a new quote above, then book.</div>
        <?php endif ?>
    </div>
</section>
