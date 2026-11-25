-- One row per booking: the workflow state machine lives in status.
-- public_id (a random UUID) is what URLs use; id never leaves the database.
-- status_changed_at shows how long a booking has been in its status, so a booking stuck
-- in CREATING can be detected. The foreign key on selected_rate_id is added in
-- 005_quote_rates.sql, once that table exists.
--
-- The CHECK constraints keep the state machine honest even if application code is wrong:
--   * status must be one of the known states;
--   * a SELECTED, CREATING, FAILED or OUTCOME_UNKNOWN booking must have a selected rate;
--   * a CREATED booking must have the Shiplogic shipment id.
-- UNIQUE shiplogic_shipment_id means one shipment can never belong to two bookings.
CREATE TABLE bookings (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id UUID NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'DRAFT',
    input_snapshot JSON NOT NULL,
    input_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    selected_rate_id BIGINT UNSIGNED NULL,
    shiplogic_shipment_id VARCHAR(64) NULL,
    tracking_reference VARCHAR(64) NULL,
    status_changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY bookings_public_id_unique (public_id),
    UNIQUE KEY bookings_shiplogic_shipment_id_unique (shiplogic_shipment_id),
    KEY bookings_user_created (user_id, created_at),
    CONSTRAINT bookings_user_fk FOREIGN KEY (user_id) REFERENCES users (id),
    CONSTRAINT bookings_status_check CHECK (
        status IN ('DRAFT', 'QUOTED', 'SELECTED', 'CREATING', 'CREATED', 'FAILED', 'OUTCOME_UNKNOWN')
    ),
    CONSTRAINT bookings_selected_rate_check CHECK (
        status NOT IN ('SELECTED', 'CREATING', 'FAILED', 'OUTCOME_UNKNOWN') OR selected_rate_id IS NOT NULL
    ),
    CONSTRAINT bookings_created_has_shipment_check CHECK (
        status <> 'CREATED' OR shiplogic_shipment_id IS NOT NULL
    )
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_uca1400_ai_ci;
