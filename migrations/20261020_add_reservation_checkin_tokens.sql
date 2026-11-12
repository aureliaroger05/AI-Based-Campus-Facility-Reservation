ALTER TABLE reservations
    ADD COLUMN checkin_token CHAR(64) DEFAULT NULL;
