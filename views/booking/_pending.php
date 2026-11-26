<?php
/**
 * A booking request in flight (CREATING) or with an unknown outcome. Included by show.php.
 *
 * @var App\Domain\Booking $booking
 * @var App\Domain\User $currentUser
 * @var string $csrfToken
 */

use App\Domain\Enums\BookingStatus;
?>
<?php if ($booking->status === BookingStatus::Creating): ?>
    <section class="card mb-4 border-warning">
        <div class="card-body">
            <h2 class="h5">Booking in progress</h2>
            <p class="mb-2">The courier is processing this booking. This usually takes a few seconds.</p>
            <a class="btn btn-outline-secondary btn-sm" href="/bookings/<?= escape($booking->publicId) ?>">Check again</a>
        </div>
    </section>
<?php else: ?>
    <section class="card mb-4 border-warning">
        <div class="card-body">
            <h2 class="h5">Waiting for confirmation</h2>
            <p>
                We sent this booking to the courier but did not get a clear answer, so we cannot tell whether
                the shipment was created. To avoid booking it twice, <strong>it will not be sent again</strong>.
                An administrator will check with the courier and update it.
            </p>
            <p class="small text-body-secondary mb-0">
                Booking reference for the courier: <code><?= escape($booking->publicId) ?></code>
            </p>

            <?php if ($currentUser->isAdmin()): ?>
                <hr>
                <h3 class="h6">Administrator: resolve</h3>
                <p class="small">
                    In the Shiplogic portal, search for a shipment with the customer reference above.
                </p>
                <form method="post" action="/bookings/<?= escape($booking->publicId) ?>/resolve" class="row g-2 align-items-end mb-3" data-lock-on-submit>
                    <input type="hidden" name="_token" value="<?= escape($csrfToken) ?>">
                    <input type="hidden" name="outcome" value="booked">
                    <div class="col-sm-5">
                        <label class="form-label small" for="shipment-id">Shipment id found</label>
                        <input class="form-control form-control-sm" id="shipment-id" name="shipment_id" maxlength="64" required>
                    </div>
                    <div class="col-sm-4">
                        <label class="form-label small" for="tracking-reference">Tracking reference (optional)</label>
                        <input class="form-control form-control-sm" id="tracking-reference" name="tracking_reference" maxlength="64">
                    </div>
                    <div class="col-sm-3">
                        <button type="submit" class="btn btn-success btn-sm w-100">It was booked</button>
                    </div>
                </form>
                <form method="post" action="/bookings/<?= escape($booking->publicId) ?>/resolve" data-lock-on-submit>
                    <input type="hidden" name="_token" value="<?= escape($csrfToken) ?>">
                    <input type="hidden" name="outcome" value="not_booked">
                    <button type="submit" class="btn btn-outline-danger btn-sm">No shipment exists: allow booking again</button>
                </form>
            <?php endif ?>
        </div>
    </section>
<?php endif ?>
