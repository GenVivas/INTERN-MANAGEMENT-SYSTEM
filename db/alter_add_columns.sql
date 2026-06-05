-- Run this in phpMyAdmin if columns don't exist yet
USE tdt_ims;

ALTER TABLE interns
    ADD COLUMN IF NOT EXISTS nationality     VARCHAR(60)  DEFAULT NULL AFTER gender,
    ADD COLUMN IF NOT EXISTS civil_status    VARCHAR(20)  DEFAULT NULL AFTER nationality,
    ADD COLUMN IF NOT EXISTS guardian_name   VARCHAR(100) DEFAULT NULL AFTER civil_status,
    ADD COLUMN IF NOT EXISTS guardian_contact VARCHAR(30) DEFAULT NULL AFTER guardian_name;
