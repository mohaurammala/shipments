-- What went out and what came back on each Shiplogic call, for tracing a call afterwards.
-- These copy personal data (names, contact details, addresses), so treat api_calls like
-- bookings. Never stored: the API key (it travels in a header) and the signature part of any
-- URL in a response (a signed label link works like a password until it expires).
-- MEDIUMTEXT, not JSON: a response that is not JSON is kept as it came, to show what was wrong.
ALTER TABLE api_calls
    ADD COLUMN request_body MEDIUMTEXT NULL AFTER outcome,
    ADD COLUMN response_body MEDIUMTEXT NULL AFTER request_body;
