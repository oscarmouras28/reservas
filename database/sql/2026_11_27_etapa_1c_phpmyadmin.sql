-- Etapa 1c: pagos.
-- Pegar en phpMyAdmin sobre la base que ya tiene las Etapas 1a y 1b. Ejecutar UNA sola vez.
SET NAMES utf8mb4;

CREATE TABLE `payments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `booking_id` bigint(20) unsigned NOT NULL,
  `environment` varchar(12) NOT NULL,
  `buy_order` varchar(26) NOT NULL,
  `token` varchar(100) DEFAULT NULL,
  `amount` int(10) unsigned NOT NULL,
  `status` varchar(20) NOT NULL,
  `response_code` smallint(6) DEFAULT NULL,
  `authorization_code` varchar(10) DEFAULT NULL,
  `payment_type_code` varchar(5) DEFAULT NULL,
  `card_last4` varchar(4) DEFAULT NULL,
  `installments` smallint(5) unsigned DEFAULT NULL,
  `transaction_date` datetime DEFAULT NULL,
  `committed_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payments_buy_order_unique` (`buy_order`),
  UNIQUE KEY `payments_token_unique` (`token`),
  KEY `payments_status_index` (`status`,`created_at`),
  KEY `payments_booking_fk` (`booking_id`),
  CONSTRAINT `payments_booking_fk` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `bookings`
  ADD `confirmed_at` datetime DEFAULT NULL AFTER `hold_expires_at`;

INSERT INTO `migrations` (`migration`, `batch`)
VALUES ('2026_11_27_000001_create_payments_table', 3);
