<?php
/**
 * Get a quote, then choose one of its services. Included by show.php.
 *
 * @var App\Domain\Booking $booking
 * @var App\Domain\Quote|null $latestQuote
 * @var App\Domain\Quote|null $quote The current quote, if it matches the booking's details.
 * @var bool $quoteExpired
 * @var DateTimeImmutable|null $quoteExpiresAt
 * @var string $csrfToken
 */

use App\Domain\Enums\BookingStatus;
use App\Domain\Quote;

$canChoose = in_array($booking->status, [BookingStatus::Quoted, BookingStatus::Selected], true) && !$quoteExpired;
$noRatesForTheseDetails = $quote === null && $latestQuote !== null
    && $latestQuote->outcome === Quote::NO_RATES && $latestQuote->inputHash === $booking->inputHash;
?>
<section class="card mb-4" id="rates">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <h2 class="h5 mb-0">Services and prices</h2>
            <form method="post" action="/bookings/<?= escape($booking->publicId) ?>/quote" data-lock-on-submit>
                <input type="hidden" name="_token" value="<?= escape($csrfToken) ?>">
                <button type="submit" class="btn btn-sm <?= $quote === null || $quoteExpired ? 'btn-primary' : 'btn-outline-secondary' ?>">
                    <?= $quote === null ? 'Get a quote' : 'Get a new quote' ?>
                </button>
            </form>
        </div>

        <?php if ($quote === null): ?>
            <?php if ($noRatesForTheseDetails): ?>
                <div class="alert alert-warning mb-0">The courier has no service for these details. Check the addresses and parcels, then try again.</div>
            <?php else: ?>
                <p class="text-body-secondary mb-0">Get a quote to see the available services and prices. Nothing is booked yet.</p>
            <?php endif ?>
        <?php else: ?>
            <p class="small text-body-secondary">
                Quoted <?= escape(local_time($quote->requestedAt)) ?>.
                <?php if ($quoteExpired): ?>
                    <strong class="text-danger">These prices have expired: get a new quote to continue.</strong>
                <?php else: ?>
                    Prices hold until <?= escape(local_time($quoteExpiresAt, 'H:i')) ?>.
                <?php endif ?>
                All prices include VAT.
            </p>
            <div class="vstack gap-2">
                <?php foreach ($quote->rates as $rate): ?>
                    <?php $chosen = $rate->id === $booking->selectedRateId; ?>
                    <div class="border rounded p-3<?= $chosen ? ' border-primary border-2' : '' ?>">
                        <div class="d-flex flex-wrap justify-content-between gap-2">
                            <div>
                                <div class="fw-semibold">
                                    <?= escape($rate->serviceName) ?>
                                    <?php if ($chosen): ?><span class="badge text-bg-primary ms-1">Chosen</span><?php endif ?>
                                </div>
                                <?php if ($rate->description !== ''): ?>
                                    <div class="small text-body-secondary"><?= escape($rate->description) ?></div>
                                <?php endif ?>
                                <?php if ($rate->deliveryFrom !== null): ?>
                                    <div class="small">
                                        Estimated delivery: <?= escape(local_time($rate->deliveryFrom)) ?>
                                        <?php if ($rate->deliveryTo !== null): ?>– <?= escape(local_time($rate->deliveryTo)) ?><?php endif ?>
                                    </div>
                                <?php endif ?>
                            </div>
                            <div class="text-sm-end">
                                <div class="fs-5 fw-semibold"><?= escape(rand_amount($rate->total)) ?></div>
                                <div class="small text-body-secondary">
                                    incl. <?= $rate->vatPercentage !== null ? escape(rtrim(rtrim(number_format($rate->vatPercentage, 2), '0'), '.')) . '% ' : '' ?>VAT
                                    <?php if ($rate->totalExcludingVat !== null): ?>
                                        <br><?= escape(rand_amount($rate->totalExcludingVat)) ?> excl. VAT
                                    <?php endif ?>
                                </div>
                            </div>
                        </div>
                        <?php if ($canChoose && !$chosen): ?>
                            <form method="post" action="/bookings/<?= escape($booking->publicId) ?>/select" class="mt-2" data-lock-on-submit>
                                <input type="hidden" name="_token" value="<?= escape($csrfToken) ?>">
                                <input type="hidden" name="rate_id" value="<?= escape($rate->id) ?>">
                                <button type="submit" class="btn btn-outline-primary btn-sm">Choose this service</button>
                            </form>
                        <?php endif ?>
                    </div>
                <?php endforeach ?>
            </div>
        <?php endif ?>
    </div>
</section>
