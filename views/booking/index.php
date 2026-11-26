<?php
/**
 * The signed-in user's bookings, newest first (admins see everyone's).
 *
 * @var list<App\Domain\BookingListItem> $items
 * @var App\Domain\User $currentUser
 */
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0">Bookings</h1>
    <a class="btn btn-primary" href="/bookings/new">New booking</a>
</div>

<?php if ($items === []): ?>
    <div class="card">
        <div class="card-body text-center py-5">
            <p class="mb-3">No bookings yet.</p>
            <a class="btn btn-primary" href="/bookings/new">Start your first booking</a>
        </div>
    </div>
<?php else: ?>
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                <tr>
                    <th scope="col">Created</th>
                    <th scope="col">Route</th>
                    <th scope="col">Parcels</th>
                    <th scope="col">Status</th>
                    <?php if ($currentUser->isAdmin()): ?>
                        <th scope="col">Owner</th>
                    <?php endif ?>
                    <th scope="col"><span class="visually-hidden">Open</span></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($items as $item): ?>
                    <?php $booking = $item->booking; $shipment = $booking->input; ?>
                    <tr>
                        <td class="text-nowrap"><?= escape(local_time($booking->createdAt, 'Y-m-d H:i')) ?></td>
                        <td><?= escape($shipment->collectionAddress->city) ?> → <?= escape($shipment->deliveryAddress->city) ?></td>
                        <td><?= count($shipment->parcels) ?></td>
                        <td><?php $status = $booking->status; require __DIR__ . '/../partials/status-badge.php'; ?></td>
                        <?php if ($currentUser->isAdmin()): ?>
                            <td class="small"><?= escape($item->ownerEmail) ?></td>
                        <?php endif ?>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-primary" href="/bookings/<?= escape($booking->publicId) ?>">Open</a>
                        </td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif ?>
