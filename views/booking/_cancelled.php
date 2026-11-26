<?php
/**
 * A shipment cancelled with the courier after booking. Included by show.php.
 *
 * @var App\Domain\Booking $booking
 * @var App\Domain\Rate|null $selectedRate
 */
?>
<section class="card mb-4 border-secondary">
    <div class="card-body">
        <h2 class="h5">Cancelled</h2>
        <p>This shipment was cancelled with the courier on <?= escape(local_time($booking->statusChangedAt)) ?>. It will not be collected, and its label can no longer be printed.</p>
        <dl class="row mb-0">
            <dt class="col-sm-4">Shipment id</dt>
            <dd class="col-sm-8"><code><?= escape($booking->shipmentId) ?></code></dd>
            <?php if ($booking->trackingReference !== null): ?>
                <dt class="col-sm-4">Tracking reference</dt>
                <dd class="col-sm-8"><code><?= escape($booking->trackingReference) ?></code></dd>
            <?php endif ?>
            <?php if ($selectedRate !== null): ?>
                <dt class="col-sm-4">Service</dt>
                <dd class="col-sm-8 mb-0"><?= escape($selectedRate->serviceName) ?></dd>
            <?php endif ?>
        </dl>
    </div>
</section>
