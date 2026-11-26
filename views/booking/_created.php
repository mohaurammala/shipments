<?php
/**
 * The booking confirmation, the printable label, and cancelling. Included by show.php.
 *
 * @var App\Domain\Booking $booking
 * @var App\Domain\Rate|null $selectedRate
 * @var DateTimeImmutable|null $labelLastOpenedAt
 * @var string $csrfToken
 */
?>
<section class="card mb-4 border-success">
    <div class="card-body">
        <h2 class="h5 text-success">Booked</h2>
        <dl class="row mb-3">
            <dt class="col-sm-4">Shipment id</dt>
            <dd class="col-sm-8"><code><?= escape($booking->shipmentId) ?></code></dd>
            <dt class="col-sm-4">Tracking reference</dt>
            <dd class="col-sm-8"><?= $booking->trackingReference !== null ? '<code>' . escape($booking->trackingReference) . '</code>' : '<span class="text-body-secondary">Not provided</span>' ?></dd>
            <?php if ($selectedRate !== null): ?>
                <dt class="col-sm-4">Service</dt>
                <dd class="col-sm-8"><?= escape($selectedRate->serviceName) ?>, <?= escape(rand_amount($selectedRate->total)) ?> incl. VAT</dd>
            <?php endif ?>
        </dl>
        <a class="btn btn-success" href="/bookings/<?= escape($booking->publicId) ?>/label" target="_blank" rel="noopener noreferrer">
            Open or print label
        </a>
        <p class="small text-body-secondary mt-2 mb-0">
            Opens the label PDF in a new tab; use the browser's print button there.
            <?php if ($labelLastOpenedAt !== null): ?>
                Last opened <?= escape(local_time($labelLastOpenedAt)) ?>.
            <?php endif ?>
        </p>

        <hr>
        <?php if ($booking->trackingReference !== null): ?>
            <form method="post" action="/bookings/<?= escape($booking->publicId) ?>/cancel" data-lock-on-submit
                  data-confirm="Cancel this shipment with the courier? This cannot be undone.">
                <input type="hidden" name="_token" value="<?= escape($csrfToken) ?>">
                <button type="submit" class="btn btn-outline-danger btn-sm">Cancel shipment</button>
            </form>
            <p class="small text-body-secondary mt-2 mb-0">Only before the courier collects it. The label can no longer be printed afterwards.</p>
        <?php else: ?>
            <p class="small text-body-secondary mb-0">Without a tracking reference this shipment can only be cancelled in the Shiplogic portal.</p>
        <?php endif ?>
    </div>
</section>
