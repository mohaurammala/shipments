<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Core\Uuid;
use App\Domain\Booking;
use App\Domain\BookingListItem;
use App\Domain\Enums\BookingStatus;
use App\Domain\ShipmentInput;
use App\Domain\User;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use RuntimeException;

/**
 * Bookings, always looked up through their owner: operators reach only their own, admins
 * reach all. The ownership filter lives here, in the query, so no controller can forget it.
 */
final readonly class BookingRepository
{
    private const string COLUMNS = 'b.id, b.public_id, b.user_id, b.status, b.input_snapshot, b.input_hash, '
        . 'b.selected_rate_id, b.shiplogic_shipment_id, b.tracking_reference, b.failure_reason, '
        . 'b.status_changed_at, b.created_at, b.updated_at';

    private const string PUBLIC_ID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/';

    public function __construct(private Database $database)
    {
    }

    public function create(User $owner, ShipmentInput $input, DateTimeImmutable $now): Booking
    {
        $publicId = Uuid::v4();
        $at = self::dateTime($now);

        $this->database->pdo()->prepare(
            'INSERT INTO bookings
                (public_id, user_id, status, input_snapshot, input_hash, status_changed_at, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
        )->execute([$publicId, $owner->id, BookingStatus::Draft->value, self::snapshot($input), $input->hash(), $at, $at, $at]);

        return $this->findForUser($publicId, $owner)
            ?? throw new RuntimeException('A booking that was just saved could not be read back.');
    }

    /**
     * Null when there is no such booking or it belongs to someone else: callers answer both
     * with the same 404, so another user's booking cannot even be confirmed to exist.
     */
    public function findForUser(string $publicId, User $user): ?Booking
    {
        $publicId = strtolower($publicId);

        // Checked first: anything that is not a UUID cannot match, and is never sent to the database.
        if (preg_match(self::PUBLIC_ID, $publicId) !== 1) {
            return null;
        }

        $sql = 'SELECT ' . self::COLUMNS . ' FROM bookings b WHERE b.public_id = ?';
        $parameters = [$publicId];

        if (!$user->isAdmin()) {
            $sql .= ' AND b.user_id = ?';
            $parameters[] = $user->id;
        }

        $statement = $this->database->pdo()->prepare($sql);
        $statement->execute($parameters);
        $row = $statement->fetch();

        return $row === false ? null : $this->toBooking($row);
    }

    /**
     * Newest first. Served by the (user_id, created_at) index for operators.
     *
     * @return list<BookingListItem>
     */
    public function listForUser(User $user, int $limit = 100): array
    {
        $sql = 'SELECT ' . self::COLUMNS . ', u.email AS owner_email FROM bookings b JOIN users u ON u.id = b.user_id';

        if (!$user->isAdmin()) {
            $sql .= ' WHERE b.user_id = :user_id';
        }

        $statement = $this->database->pdo()->prepare($sql . ' ORDER BY b.created_at DESC, b.id DESC LIMIT :limit');

        if (!$user->isAdmin()) {
            $statement->bindValue('user_id', $user->id, PDO::PARAM_INT);
        }

        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return array_map(
            fn (array $row): BookingListItem => new BookingListItem($this->toBooking($row), (string) $row['owner_email']),
            $statement->fetchAll(),
        );
    }

    /**
     * Saves new details and sends the booking back to DRAFT, clearing any selected rate: a
     * quote only holds for the input it was made for. The status check is part of the
     * UPDATE itself, so an edit can never slip in after a booking request has started.
     *
     * @return bool False when the booking had already moved past the editable statuses.
     */
    public function replaceInput(Booking $booking, ShipmentInput $input, DateTimeImmutable $now): bool
    {
        $editable = array_values(array_filter(
            BookingStatus::cases(),
            static fn (BookingStatus $status): bool => $status->isEditable(),
        ));
        $placeholders = implode(', ', array_fill(0, count($editable), '?'));
        $at = self::dateTime($now);

        $statement = $this->database->pdo()->prepare(
            "UPDATE bookings
             SET input_snapshot = ?, input_hash = ?, status = ?, selected_rate_id = NULL, failure_reason = NULL,
                 status_changed_at = ?, updated_at = ?
             WHERE id = ? AND status IN ({$placeholders})",
        );
        $statement->execute([
            self::snapshot($input),
            $input->hash(),
            BookingStatus::Draft->value,
            $at,
            $at,
            $booking->id,
            ...array_map(fn (BookingStatus $status) => $status->value, $editable),
        ]);

        return $statement->rowCount() === 1;
    }

    /**
     * After a quote: QUOTED with rates, or back to DRAFT without any. Only if the details are
     * still the ones that were quoted (the user may have edited them meanwhile) and no
     * booking request has started.
     */
    public function afterQuote(Booking $booking, BookingStatus $to, DateTimeImmutable $now): bool
    {
        return $this->transition(
            $booking,
            from: self::editable(),
            to: $to,
            set: ['selected_rate_id' => null, 'failure_reason' => null],
            now: $now,
            where: [['input_hash = ?', $booking->inputHash]],
        );
    }

    public function select(Booking $booking, int $rateId, DateTimeImmutable $now): bool
    {
        return $this->transition(
            $booking,
            from: [BookingStatus::Quoted, BookingStatus::Selected],
            to: BookingStatus::Selected,
            set: ['selected_rate_id' => $rateId],
            now: $now,
            where: [['input_hash = ?', $booking->inputHash]],
        );
    }

    /**
     * The duplicate-booking guard. One UPDATE moves the booking to CREATING only if it is
     * still SELECTED (or FAILED), with the same details and the same chosen rate. However
     * many requests arrive at once, the database lets exactly one of them change the row;
     * every other one gets false and must not call Shiplogic.
     *
     * #[\NoDiscard]: ignoring the answer would defeat the guard, so NoDiscard warns and the warning is truned to an exception by error handler).
     */
    #[\NoDiscard]
    public function claim(Booking $booking, DateTimeImmutable $now): bool
    {
        return $this->transition(
            $booking,
            from: [BookingStatus::Selected, BookingStatus::Failed],
            to: BookingStatus::Creating,
            set: ['failure_reason' => null],
            now: $now,
            where: [['input_hash = ?', $booking->inputHash], ['selected_rate_id = ?', $booking->selectedRateId]],
        );
    }

    /**
     * @param BookingStatus $from CREATING after a booking request, OUTCOME_UNKNOWN when an
     *                            admin confirms a shipment found in the portal.
     */
    public function markCreated(
        Booking $booking,
        string $shipmentId,
        ?string $trackingReference,
        DateTimeImmutable $now,
        BookingStatus $from = BookingStatus::Creating,
    ): bool {
        return $this->transition(
            $booking,
            from: [$from],
            to: BookingStatus::Created,
            set: ['shiplogic_shipment_id' => $shipmentId, 'tracking_reference' => $trackingReference, 'failure_reason' => null],
            now: $now,
        );
    }

    /**
     * @param string $reason Safe to show the user (never Shiplogic's own text).
     */
    public function markFailed(
        Booking $booking,
        string $reason,
        DateTimeImmutable $now,
        BookingStatus $from = BookingStatus::Creating,
    ): bool {
        return $this->transition(
            $booking,
            from: [$from],
            to: BookingStatus::Failed,
            set: ['failure_reason' => mb_substr($reason, 0, 255)],
            now: $now,
        );
    }

    /**
     * @param DateTimeImmutable|null $stuckSince Only if the booking has been CREATING since
     *                                           at least then (the request that claimed it
     *                                           died without recording an outcome).
     */
    public function markUnknown(Booking $booking, DateTimeImmutable $now, ?DateTimeImmutable $stuckSince = null): bool
    {
        return $this->transition(
            $booking,
            from: [BookingStatus::Creating],
            to: BookingStatus::OutcomeUnknown,
            set: [],
            now: $now,
            where: $stuckSince === null ? [] : [['status_changed_at <= ?', self::dateTime($stuckSince)]],
        );
    }

    /**
     * After the courier confirmed the cancellation. The shipment id and tracking reference
     * stay: the shipment still exists at Shiplogic, with a cancelled status.
     */
    public function markCancelled(Booking $booking, DateTimeImmutable $now): bool
    {
        return $this->transition(
            $booking,
            from: [BookingStatus::Created],
            to: BookingStatus::Cancelled,
            set: [],
            now: $now,
        );
    }

    /**
     * One conditional UPDATE: it changes the row only if the booking is still in one of the
     * $from statuses (and any extra conditions hold), and reports whether it did. Column
     * names come only from this class, never from input.
     *
     * @param list<BookingStatus> $from
     * @param array<string, scalar|null> $set
     * @param list<array{string, scalar|null}> $where
     */
    private function transition(
        Booking $booking, array $from, BookingStatus $to, array $set, DateTimeImmutable $now, array $where = [],
    ): bool {
        $at = self::dateTime($now);
        $assignments = ['status = ?', 'status_changed_at = ?', 'updated_at = ?'];
        $parameters = [$to->value, $at, $at];

        foreach ($set as $column => $value) {
            $assignments[] = "{$column} = ?";
            $parameters[] = $value;
        }

        $conditions = ['id = ?'];
        $parameters[] = $booking->id;

        foreach ($where as [$condition, $value]) {
            $conditions[] = $condition;
            $parameters[] = $value;
        }

        $conditions[] = 'status IN (' . implode(', ', array_fill(0, count($from), '?')) . ')';
        $parameters = [...$parameters, ...array_map(fn (BookingStatus $status) => $status->value, $from)];


        $assignments = implode(', ', $assignments);
        $conditions = implode(' AND ', $conditions);
        $statement = $this->database->pdo()->prepare(
            "UPDATE bookings SET {$assignments} WHERE {$conditions}",
        );
        $statement->execute($parameters);

        return $statement->rowCount() === 1;
    }

    /**
     * @return list<BookingStatus>
     */
    private static function editable(): array
    {
        return array_values(array_filter(
            BookingStatus::cases(),
            static fn (BookingStatus $status): bool => $status->isEditable(),
        ));
    }

    private static function snapshot(ShipmentInput $input): string
    {
        return json_encode(
            $input->toArray(),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR,
        );
    }

    private static function dateTime(DateTimeImmutable $at): string
    {
        return $at->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    /**
     * @param array<string, mixed> $row
     */
    private function toBooking(array $row): Booking
    {
        $utc = new DateTimeZone('UTC');
        $snapshot = json_decode((string) $row['input_snapshot'], true, flags: JSON_THROW_ON_ERROR);

        return new Booking(
            id: (int) $row['id'],
            publicId: (string) $row['public_id'],
            userId: (int) $row['user_id'],
            status: BookingStatus::from((string) $row['status']),
            input: ShipmentInput::fromArray($snapshot),
            inputHash: (string) $row['input_hash'],
            selectedRateId: $row['selected_rate_id'] === null ? null : (int) $row['selected_rate_id'],
            shipmentId: $row['shiplogic_shipment_id'] === null ? null : (string) $row['shiplogic_shipment_id'],
            trackingReference: $row['tracking_reference'] === null ? null : (string) $row['tracking_reference'],
            statusChangedAt: new DateTimeImmutable((string) $row['status_changed_at'], $utc),
            createdAt: new DateTimeImmutable((string) $row['created_at'], $utc),
            updatedAt: new DateTimeImmutable((string) $row['updated_at'], $utc),
            failureReason: $row['failure_reason'] === null ? null : (string) $row['failure_reason'],
        );
    }
}
