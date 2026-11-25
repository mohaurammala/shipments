-- One row per call to Shiplogic: metadata only. No request or response bodies (they hold
-- addresses and contact details) and no full URLs (a query string can carry ids).
-- endpoint is the path pattern, e.g. /shipments/label/stickers.
CREATE TABLE api_calls (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    booking_id BIGINT UNSIGNED NULL,
    method VARCHAR(8) NOT NULL,
    endpoint VARCHAR(255) NOT NULL,
    http_status SMALLINT UNSIGNED NULL,
    duration_ms INT UNSIGNED NOT NULL,
    outcome VARCHAR(16) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY api_calls_booking_endpoint (booking_id, endpoint),
    CONSTRAINT api_calls_booking_fk FOREIGN KEY (booking_id) REFERENCES bookings (id),
    CONSTRAINT api_calls_outcome_check CHECK (
        outcome IN ('ok', 'http_error', 'timeout', 'network', 'bad_response')
    )
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_uca1400_ai_ci;
