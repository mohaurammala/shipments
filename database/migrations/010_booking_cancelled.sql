-- A booked shipment can be cancelled with the courier (POST /shipments/cancel). The status
-- check gains CANCELLED, and a cancelled booking, like a created one, must keep the shipment
-- id it was booked under: the shipment still exists at Shiplogic, only with a cancelled status.
ALTER TABLE bookings DROP CONSTRAINT bookings_status_check;

ALTER TABLE bookings ADD CONSTRAINT bookings_status_check CHECK (
    status IN ('DRAFT', 'QUOTED', 'SELECTED', 'CREATING', 'CREATED', 'FAILED', 'OUTCOME_UNKNOWN', 'CANCELLED')
);

ALTER TABLE bookings DROP CONSTRAINT bookings_created_has_shipment_check;

ALTER TABLE bookings ADD CONSTRAINT bookings_created_has_shipment_check CHECK (
    status NOT IN ('CREATED', 'CANCELLED') OR shiplogic_shipment_id IS NOT NULL
);
