<?php
/**
 * Included by other templates.
 *
 * @var App\Domain\Enums\BookingStatus $status
 */

use App\Domain\Enums\BookingStatus;

[$badgeLabel, $badgeClass] = match ($status) {
    BookingStatus::Draft => ['Draft', 'text-bg-secondary'],
    BookingStatus::Quoted => ['Quoted', 'text-bg-info'],
    BookingStatus::Selected => ['Service chosen', 'text-bg-primary'],
    BookingStatus::Creating => ['Booking…', 'text-bg-warning'],
    BookingStatus::Created => ['Booked', 'text-bg-success'],
    BookingStatus::Failed => ['Not booked', 'text-bg-danger'],
    BookingStatus::OutcomeUnknown => ['Checking', 'text-bg-warning'],
    BookingStatus::Cancelled => ['Cancelled', 'text-bg-dark'],
};
?>
<span class="badge <?= $badgeClass ?>"><?= escape($badgeLabel) ?></span>
