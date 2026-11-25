-- Every attempt to fetch a booking's label. A history, not a cache: label URLs are often
-- signed and expire, so the app fetches a fresh one each time the user asks.
-- pdf_url leads to customer addresses, so it is read only through ownership checks and
-- never logged.
CREATE TABLE labels (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    booking_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(16) NOT NULL,
    pdf_url VARCHAR(2048) NULL,
    error_code VARCHAR(64) NULL,
    requested_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY labels_booking_latest (booking_id, id),
    CONSTRAINT labels_booking_fk FOREIGN KEY (booking_id) REFERENCES bookings (id),
    CONSTRAINT labels_requested_by_fk FOREIGN KEY (requested_by) REFERENCES users (id),
    CONSTRAINT labels_status_check CHECK (status IN ('ok', 'failed')),
    CONSTRAINT labels_ok_has_url_check CHECK (status <> 'ok' OR pdf_url IS NOT NULL)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
