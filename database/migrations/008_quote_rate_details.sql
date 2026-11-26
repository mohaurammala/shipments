-- What POST /rates actually returns (docs/API-NOTES.md): the price excluding VAT and the VAT
-- rate, the service level's id and description, and delivery estimates with times (same-day
-- services differ by hours, not days). Times are stored in UTC.
ALTER TABLE quote_rates
    ADD COLUMN service_level_id BIGINT UNSIGNED NULL AFTER service_level_code,
    ADD COLUMN service_description VARCHAR(500) NOT NULL DEFAULT '' AFTER service_name,
    ADD COLUMN amount_excluding_vat DECIMAL(12, 2) NULL AFTER total_amount,
    ADD COLUMN vat_percentage DECIMAL(5, 2) NULL AFTER vat_treatment,
    MODIFY estimated_delivery_from DATETIME NULL,
    MODIFY estimated_delivery_to DATETIME NULL,
    ADD COLUMN collection_cut_off DATETIME NULL AFTER estimated_delivery_to,
    ADD CONSTRAINT quote_rates_excluding_vat_check CHECK (
        amount_excluding_vat IS NULL OR (amount_excluding_vat >= 0 AND amount_excluding_vat <= total_amount)
    );
