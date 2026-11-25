-- Every call to POST /rates for a booking. Quotes are never overwritten, so the history
-- shows exactly what was offered when. input_hash records which inputs were quoted: a
-- selection is only valid while it matches the booking's current input_hash.
CREATE TABLE quotes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    booking_id BIGINT UNSIGNED NOT NULL,
    input_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    outcome VARCHAR(16) NOT NULL,
    requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY quotes_booking_latest (booking_id, id),
    CONSTRAINT quotes_booking_fk FOREIGN KEY (booking_id) REFERENCES bookings (id),
    CONSTRAINT quotes_outcome_check CHECK (outcome IN ('ok', 'no_rates', 'error'))
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_uca1400_ai_ci;
