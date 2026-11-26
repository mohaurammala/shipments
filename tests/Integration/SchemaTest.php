<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\Attributes\Test;

/**
 * The database's own rules: even if application code has a bug, these writes are refused.
 */
final class SchemaTest extends DatabaseTestCase
{
    #[Test]
    public function aValidDraftBookingIsAccepted(): void
    {
        $bookingId = $this->createBooking($this->createUser());

        $row = $this->db->query("SELECT status, public_id FROM bookings WHERE id = {$bookingId}")->fetch();

        self::assertSame('DRAFT', $row['status']);
        self::assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $row['public_id']);
    }

    #[Test]
    public function anUnknownStatusIsRefused(): void
    {
        $userId = $this->createUser();

        $this->assertRefusedBy('bookings_status_check', fn () => $this->createBooking($userId, ['status' => 'BOGUS']));
    }

    #[Test]
    public function aSelectedBookingMustHaveASelectedRate(): void
    {
        $userId = $this->createUser();

        $this->assertRefusedBy(
            'bookings_selected_rate_check',
            fn () => $this->createBooking($userId, ['status' => 'SELECTED']),
        );
    }

    #[Test]
    public function aCreatedBookingMustHaveItsShipmentId(): void
    {
        $userId = $this->createUser();
        $rateId = $this->createRate($this->createBooking($userId));

        $this->assertRefusedBy(
            'bookings_created_has_shipment_check',
            fn () => $this->createBooking($userId, ['status' => 'CREATED', 'selected_rate_id' => $rateId]),
        );
    }

    #[Test]
    public function aCancelledBookingKeepsItsShipmentId(): void
    {
        $userId = $this->createUser();
        $rateId = $this->createRate($this->createBooking($userId));

        $this->assertRefusedBy(
            'bookings_created_has_shipment_check',
            fn () => $this->createBooking($userId, ['status' => 'CANCELLED', 'selected_rate_id' => $rateId]),
        );

        $this->createBooking($userId, ['status' => 'CANCELLED', 'selected_rate_id' => $rateId, 'shiplogic_shipment_id' => 'SL-1002']);
        $this->addToAssertionCount(1);
    }

    #[Test]
    public function oneShipmentCanNeverBelongToTwoBookings(): void
    {
        $userId = $this->createUser();
        $rateId = $this->createRate($this->createBooking($userId));
        $created = ['status' => 'CREATED', 'selected_rate_id' => $rateId, 'shiplogic_shipment_id' => 'SL-1001'];

        $this->createBooking($userId, $created);

        $this->assertRefusedBy(
            'bookings_shiplogic_shipment_id_unique',
            fn () => $this->createBooking($userId, $created),
        );
    }

    #[Test]
    public function aBookingCannotPointAtARateThatDoesNotExist(): void
    {
        $userId = $this->createUser();

        $this->assertRefusedBy(
            'bookings_selected_rate_fk',
            fn () => $this->createBooking($userId, ['status' => 'SELECTED', 'selected_rate_id' => 999_999]),
        );
    }

    #[Test]
    public function aBookingMustBelongToAnExistingUser(): void
    {
        $this->assertRefusedBy('bookings_user_fk', fn () => $this->createBooking(999_999));
    }

    #[Test]
    public function theInputSnapshotMustBeValidJson(): void
    {
        $userId = $this->createUser();

        $this->assertRefusedBy('input_snapshot', fn () => $this->createBooking($userId, ['input_snapshot' => '{not json']));
    }

    #[Test]
    public function emailsAreUniqueWhateverTheirCaseOrAccents(): void
    {
        $this->createUser('josé@example.test');

        $this->assertRefusedBy('users_unique', fn () => $this->createUser('JOSE@example.test'));
    }

    #[Test]
    public function onlyKnownRolesAreAccepted(): void
    {
        $this->assertRefusedBy('users_role_check', fn () => $this->createUser('root@example.test', 'superuser'));
    }

    #[Test]
    public function aRateCannotBeNegative(): void
    {
        $bookingId = $this->createBooking($this->createUser());

        $this->assertRefusedBy('quote_rates_total_check', fn () => $this->createRate($bookingId, '-0.01'));
    }

    #[Test]
    public function moneyKeepsExactCents(): void
    {
        $rateId = $this->createRate($this->createBooking($this->createUser()), '0.10');
        $this->db->exec("UPDATE quote_rates SET total_amount = total_amount + 0.20 WHERE id = {$rateId}");

        $total = $this->db->query("SELECT total_amount FROM quote_rates WHERE id = {$rateId}")->fetchColumn();

        self::assertSame('0.30', $total);
    }

    #[Test]
    public function aSuccessfulLabelMustHaveAUrl(): void
    {
        $userId = $this->createUser();
        $bookingId = $this->createBooking($userId);

        $this->assertRefusedBy('labels_ok_has_url_check', fn () => $this->db->prepare(
            "INSERT INTO labels (booking_id, status, requested_by) VALUES (?, 'ok', ?)",
        )->execute([$bookingId, $userId]));
    }

    #[Test]
    public function apiCallOutcomesAreFromTheKnownList(): void
    {
        $this->assertRefusedBy('api_calls_outcome_check', fn () => $this->db->exec(
            "INSERT INTO api_calls (method, endpoint, duration_ms, outcome) VALUES ('POST', '/rates', 120, 'maybe')",
        ));
    }
}
