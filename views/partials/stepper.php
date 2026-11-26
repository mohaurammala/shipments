<?php
/**
 * Progress through the five features, worked out from the booking's status.
 * Included by other templates; not shown for a cancelled booking.
 *
 * @var App\Domain\Enums\BookingStatus|null $status Null while entering a new booking.
 */

use App\Domain\Enums\BookingStatus;

$steps = ['Details', 'Quote', 'Service', 'Book', 'Label'];
$currentStep = match ($status) {
    null => 0,
    BookingStatus::Draft => 1,
    BookingStatus::Quoted => 2,
    BookingStatus::Selected, BookingStatus::Failed, BookingStatus::Creating, BookingStatus::OutcomeUnknown => 3,
    BookingStatus::Created, BookingStatus::Cancelled => 4,
};
?>
<ol class="stepper list-unstyled d-flex flex-wrap gap-2 mb-4" aria-label="Booking progress">
    <?php foreach ($steps as $index => $step): ?>
        <?php
        $state = $index < $currentStep ? 'done' : ($index === $currentStep ? 'current' : 'todo');
        $class = ['done' => 'text-bg-success', 'current' => 'text-bg-primary', 'todo' => 'text-bg-light border'][$state];
        ?>
        <li class="badge rounded-pill fw-normal fs-6 <?= $class ?>"<?= $state === 'current' ? ' aria-current="step"' : '' ?>>
            <?= $index + 1 ?>. <?= escape($step) ?>
            <?php if ($state === 'done'): ?><span class="visually-hidden">(done)</span><?php endif ?>
        </li>
    <?php endforeach ?>
</ol>
