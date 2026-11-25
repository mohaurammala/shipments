-- The services offered in one quote. Money is DECIMAL, never FLOAT: 0.1 + 0.2 must be 0.3.
-- vat_treatment has no CHECK yet: its possible values depend on the rates response, which
-- is confirmed against the API documentation in A7.
CREATE TABLE quote_rates (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    quote_id BIGINT UNSIGNED NOT NULL,
    service_level_code VARCHAR(64) NOT NULL,
    service_name VARCHAR(255) NOT NULL,
    total_amount DECIMAL(12, 2) NOT NULL,
    vat_treatment VARCHAR(16) NOT NULL,
    estimated_delivery_from DATE NULL,
    estimated_delivery_to DATE NULL,
    PRIMARY KEY (id),
    CONSTRAINT quote_rates_quote_fk FOREIGN KEY (quote_id) REFERENCES quotes (id),
    CONSTRAINT quote_rates_total_check CHECK (total_amount >= 0)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- bookings and quote_rates refer to each other, so this link can only be added now.
ALTER TABLE bookings
    ADD CONSTRAINT bookings_selected_rate_fk FOREIGN KEY (selected_rate_id) REFERENCES quote_rates (id);
