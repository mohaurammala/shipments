<?php

declare(strict_types=1);

namespace App\Domain\Enums;

/**
 * The booking workflow (DESIGN.md §3). The values match the bookings.status CHECK
 * constraint in the bookings table
 */
enum BookingStatus: string
{
    case Draft = 'DRAFT';
    case Quoted = 'QUOTED';
    case Selected = 'SELECTED';
    case Creating = 'CREATING';
    case Created = 'CREATED';
    case Failed = 'FAILED';
    case OutcomeUnknown = 'OUTCOME_UNKNOWN';

    /**
     * Where a booking may go from here. Staying in the same status counts as a move when
     * something about the booking changes: editing a draft, re-quoting, choosing another rate.
     *
     * @return list<self>
     */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Draft => [self::Draft, self::Quoted],
            self::Quoted => [self::Draft, self::Quoted, self::Selected],
            self::Selected => [self::Draft, self::Quoted, self::Selected, self::Creating],
            self::Creating => [self::Created, self::Failed, self::OutcomeUnknown],
            self::Failed => [self::Draft, self::Quoted, self::Creating],
            self::OutcomeUnknown => [self::Created, self::Failed],
            self::Created => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedNext(), true);
    }

    /**
     * The shipment details can still be changed (which sends the booking back to Draft).
     * Never while a booking request is in flight or its outcome is unknown.
     */
    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::Quoted, self::Selected, self::Failed], true);
    }

    public function isFinal(): bool
    {
        return $this->allowedNext() === [];
    }
}
