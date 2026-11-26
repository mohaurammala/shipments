<?php
/**
 * The shipment at a glance: beside the page on wide screens, below it on narrow ones.
 * Included by other templates.
 *
 * @var App\Domain\Booking $booking
 */

$shipment = $booking->input;
$totalWeight = array_sum(array_map(static fn ($parcel): float => $parcel->weightKg, $shipment->parcels));
$parcelCount = count($shipment->parcels);
$declared = $shipment->declaredValue->cents / 100;
?>
<div class="card">
    <div class="card-body">
        <h2 class="h6 card-title d-flex justify-content-between align-items-center">
            Shipment summary
            <?php $status = $booking->status; require __DIR__ . '/status-badge.php'; ?>
        </h2>
        <dl class="mb-0 small">
            <dt>From</dt>
            <dd><?= escape($shipment->collectionAddress->city) ?>, <?= escape($shipment->collectionAddress->province->label()) ?></dd>
            <dt>To</dt>
            <dd><?= escape($shipment->deliveryAddress->city) ?>, <?= escape($shipment->deliveryAddress->province->label()) ?></dd>
            <dt>Parcels</dt>
            <dd><?= $parcelCount ?> (<?= escape(rtrim(rtrim(number_format($totalWeight, 2, '.', ' '), '0'), '.')) ?> kg in total)</dd>
            <dt>Declared value</dt>
            <dd class="mb-0">R <?= escape(number_format($declared, 2, '.', ' ')) ?></dd>
        </dl>
    </div>
</div>
