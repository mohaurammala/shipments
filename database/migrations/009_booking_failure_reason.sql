-- Why a booking attempt failed, in words safe to show the user (never Shiplogic's raw text).
-- Shown on the booking page until the next attempt or edit clears it.
ALTER TABLE bookings
    ADD COLUMN failure_reason VARCHAR(255) NULL AFTER tracking_reference;
