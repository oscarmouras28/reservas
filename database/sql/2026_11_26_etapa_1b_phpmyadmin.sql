-- Etapa 1b: datos del paciente y consentimiento en "bookings".
-- Pegar en phpMyAdmin sobre la base que ya tiene la Etapa 1a. Ejecutar UNA sola vez.
SET NAMES utf8mb4;

ALTER TABLE `bookings`
  ADD `public_id` char(26) NULL AFTER `id`,
  ADD `patient_name` varchar(120) NULL AFTER `price`,
  ADD `document_type` varchar(10) NULL AFTER `patient_name`,
  ADD `document_number` text NULL AFTER `document_type`,
  ADD `phone` text NULL AFTER `document_number`,
  ADD `email` varchar(190) NULL AFTER `phone`,
  ADD `address` text NULL AFTER `email`,
  ADD `consent_accepted_at` datetime NULL AFTER `address`,
  ADD `consent_version` varchar(20) NULL AFTER `consent_accepted_at`,
  ADD `consent_text` text NULL AFTER `consent_version`,
  ADD UNIQUE `bookings_public_id_unique` (`public_id`);

INSERT INTO `migrations` (`migration`, `batch`)
VALUES ('2026_11_26_000001_add_patient_data_to_bookings_table', 2);
