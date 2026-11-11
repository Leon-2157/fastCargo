-- =========================================================================
-- FastCargo Enterprise — Master Database Schema (MySQL / phpMyAdmin)
-- Engine: InnoDB | Charset: utf8mb4 | Collation: utf8mb4_unicode_ci
-- Compatible with MySQL 8.0+, MariaDB 10.4+, and phpMyAdmin
-- =========================================================================

CREATE DATABASE IF NOT EXISTS `FastCargo` 
    CHARACTER SET utf8mb4 
    COLLATE utf8mb4_unicode_ci;

USE `FastCargo`;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- -------------------------------------------------------------------------
-- 1. MASTER & CUSTOMER MODULE (Eks Skema public)
-- -------------------------------------------------------------------------

-- Tabel: users
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
    `id` CHAR(36) NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `username` VARCHAR(255) NULL,
    `email` VARCHAR(255) NOT NULL,
    `phone` VARCHAR(50) NULL,
    `password` VARCHAR(255) NOT NULL,
    `user_type` VARCHAR(50) NOT NULL DEFAULT 'RETAIL_CUSTOMER', -- RETAIL_CUSTOMER, CORPORATE_USER, OPERATOR_USER, ADMIN_USER
    `admin_role` VARCHAR(50) NULL,                             -- SUPER_ADMIN, FINANCE_ADMIN, DISPATCH_ADMIN, COMPLIANCE_ADMIN
    `status` VARCHAR(50) NOT NULL DEFAULT 'ACTIVE',             -- ACTIVE, BOOKING_LOCKED, BLACKLISTED, SUSPENDED
    `two_factor_secret` VARCHAR(255) NULL,
    `two_factor_enabled_at` TIMESTAMP NULL DEFAULT NULL,
    `nik` VARCHAR(30) NULL,
    `kyc_aml_status` VARCHAR(50) NOT NULL DEFAULT 'UNVERIFIED', -- UNVERIFIED, PENDING, VERIFIED, REJECTED
    `ktp_verified_at` TIMESTAMP NULL DEFAULT NULL,
    `ktp_file_url` VARCHAR(255) NULL,
    `business_type` VARCHAR(50) NULL,
    `email_verified_at` TIMESTAMP NULL DEFAULT NULL,
    `remember_token` VARCHAR(100) NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `users_email_unique` (`email`),
    UNIQUE KEY `users_username_unique` (`username`),
    INDEX `idx_users_type_status` (`user_type`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabel: addresses
DROP TABLE IF EXISTS `addresses`;
CREATE TABLE `addresses` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` CHAR(36) NOT NULL,
    `type` VARCHAR(50) NOT NULL DEFAULT 'personal',             -- personal, company, billing, warehouse
    `country` VARCHAR(100) NOT NULL,
    `country_code` VARCHAR(10) NULL,
    `region` VARCHAR(100) NULL,
    `city` VARCHAR(100) NULL,
    `street` TEXT NOT NULL,
    `postal_code` VARCHAR(20) NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_addresses_user_id` (`user_id`),
    CONSTRAINT `fk_addresses_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabel: companies
DROP TABLE IF EXISTS `companies`;
CREATE TABLE `companies` (
    `id` CHAR(36) NOT NULL,
    `user_id` CHAR(36) NOT NULL,
    `company_name` VARCHAR(255) NOT NULL,
    `nib` VARCHAR(50) NULL,
    `npwp` VARCHAR(50) NULL,
    `company_address` TEXT NULL,
    `pic_name` VARCHAR(255) NULL,
    `pic_phone` VARCHAR(50) NULL,
    `email` VARCHAR(255) NULL,
    `industry` VARCHAR(100) NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `companies_nib_unique` (`nib`),
    UNIQUE KEY `companies_npwp_unique` (`npwp`),
    KEY `idx_companies_user_id` (`user_id`),
    CONSTRAINT `fk_companies_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabel: ports
DROP TABLE IF EXISTS `ports`;
CREATE TABLE `ports` (
    `id` VARCHAR(10) NOT NULL,                                  -- e.g. IDJKT, IDSUB, IDBTM, SGSIN
    `name` VARCHAR(255) NOT NULL,
    `city` VARCHAR(100) NOT NULL,
    `country_code` VARCHAR(5) NOT NULL DEFAULT 'ID',
    `port_type` VARCHAR(50) NOT NULL DEFAULT 'SEAPORT',         -- SEAPORT, AIRPORT, LAND_HUB
    `latitude` DECIMAL(10, 7) NOT NULL,
    `longitude` DECIMAL(10, 7) NOT NULL,
    `terminal_cy_capacity_teus` INT NOT NULL DEFAULT 5000,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_ports_country_type` (`country_code`, `port_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabel: routes
DROP TABLE IF EXISTS `routes`;
CREATE TABLE `routes` (
    `id` CHAR(36) NOT NULL,
    `origin_port_id` VARCHAR(10) NOT NULL,
    `destination_port_id` VARCHAR(10) NOT NULL,
    `transport_mode` VARCHAR(50) NOT NULL DEFAULT 'SEA_CONTAINER', -- SEA_RORO, SEA_CONTAINER, LAND_TRUCK, AIR_CARGO
    `base_rate_per_kg` DECIMAL(12, 2) NOT NULL,
    `base_rate_per_cbm` DECIMAL(12, 2) NOT NULL,
    `estimated_transit_hours` INT NOT NULL,
    `is_weather_hold` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_routes_origin` (`origin_port_id`),
    KEY `idx_routes_destination` (`destination_port_id`),
    CONSTRAINT `fk_routes_origin_port` FOREIGN KEY (`origin_port_id`) REFERENCES `ports` (`id`),
    CONSTRAINT `fk_routes_dest_port` FOREIGN KEY (`destination_port_id`) REFERENCES `ports` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabel: hs_codes
DROP TABLE IF EXISTS `hs_codes`;
CREATE TABLE `hs_codes` (
    `code` VARCHAR(15) NOT NULL,                                -- e.g. 0901.11.10
    `description_id` TEXT NOT NULL,
    `description_en` TEXT NULL,
    `import_duty_pct` DECIMAL(5, 2) NOT NULL DEFAULT 0.00,
    `vat_pct` DECIMAL(5, 2) NOT NULL DEFAULT 11.00,
    `income_tax_pct` DECIMAL(5, 2) NOT NULL DEFAULT 2.50,
    `has_lartas_restriction` TINYINT(1) NOT NULL DEFAULT 0,
    `required_permit_agency` VARCHAR(100) NULL,                 -- BPOM, Kemenperin, Kementan
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabel: exchange_rates
DROP TABLE IF EXISTS `exchange_rates`;
CREATE TABLE `exchange_rates` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `currency_pair` VARCHAR(10) NOT NULL,                       -- USD/IDR, SGD/IDR, MYR/IDR
    `rate` DECIMAL(14, 4) NOT NULL,
    `locked_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `expires_at` TIMESTAMP NOT NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_exchange_rates_pair` (`currency_pair`, `expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -------------------------------------------------------------------------
-- 2. OPERATIONAL MODULE (Eks Skema ops)
-- -------------------------------------------------------------------------

-- Tabel: ops_operator_profiles
DROP TABLE IF EXISTS `ops_operator_profiles`;
CREATE TABLE `ops_operator_profiles` (
    `id` CHAR(36) NOT NULL,
    `user_id` CHAR(36) NOT NULL,
    `employee_id` VARCHAR(50) NOT NULL,                         -- OPS-JKT-042
    `assigned_hub_id` VARCHAR(10) NOT NULL,
    `role_tier` VARCHAR(50) NOT NULL DEFAULT 'OPERATOR_DOMESTIC', -- OPERATOR_DOMESTIC, OPERATOR_INTERNATIONAL
    `wireguard_pubkey` VARCHAR(255) NULL,
    `device_fingerprint` VARCHAR(255) NULL,
    `kpi_rating` DECIMAL(3, 2) NOT NULL DEFAULT 5.00,
    `is_on_duty` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `ops_operator_employee_unique` (`employee_id`),
    KEY `idx_ops_operator_user` (`user_id`),
    KEY `idx_ops_operator_hub` (`assigned_hub_id`),
    CONSTRAINT `fk_ops_operator_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ops_operator_hub` FOREIGN KEY (`assigned_hub_id`) REFERENCES `ports` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabel: ops_cargo_bookings
DROP TABLE IF EXISTS `ops_cargo_bookings`;
CREATE TABLE `ops_cargo_bookings` (
    `id` CHAR(36) NOT NULL,
    `tracking_number` VARCHAR(50) NOT NULL,                     -- RPN-20261106-001
    `shipper_id` CHAR(36) NOT NULL,
    `company_id` CHAR(36) NULL,
    `route_id` CHAR(36) NOT NULL,
    `hs_code` VARCHAR(15) NULL,
    `booking_type` VARCHAR(50) NOT NULL DEFAULT 'RETAIL_LCL',   -- RETAIL_LCL, CORPORATE_FCL, CHARTER_TRUCK
    `cargo_type` VARCHAR(50) NOT NULL DEFAULT 'GENERAL_CARGO',  -- GENERAL_CARGO, DANGEROUS_GOODS, REEFER_PERISHABLE
    `total_weight_kg` DECIMAL(10, 2) NOT NULL,
    `total_volume_cbm` DECIMAL(10, 2) NOT NULL,
    `declared_goods_value` DECIMAL(16, 2) NOT NULL,
    `currency` VARCHAR(10) NOT NULL DEFAULT 'IDR',
    `cargo_status` VARCHAR(50) NOT NULL DEFAULT 'BOOKED',       -- DRAFT, BOOKED, GATE_IN, CUSTOMS_INSPECTION, SAILING, DELIVERED, WEATHER_HOLD, CLAIM_HOLD, CANCELLED
    `customs_channel` VARCHAR(20) NULL,                         -- GREEN_CHANNEL, YELLOW_CHANNEL, RED_CHANNEL
    `assigned_operator_id` CHAR(36) NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `ops_cargo_tracking_unique` (`tracking_number`),
    KEY `idx_ops_cargo_shipper` (`shipper_id`),
    KEY `idx_ops_cargo_company` (`company_id`),
    KEY `idx_ops_cargo_route` (`route_id`),
    KEY `idx_ops_cargo_hs` (`hs_code`),
    KEY `idx_ops_cargo_operator` (`assigned_operator_id`),
    KEY `idx_ops_cargo_status` (`cargo_status`),
    CONSTRAINT `fk_ops_cargo_shipper` FOREIGN KEY (`shipper_id`) REFERENCES `users` (`id`),
    CONSTRAINT `fk_ops_cargo_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_ops_cargo_route` FOREIGN KEY (`route_id`) REFERENCES `routes` (`id`),
    CONSTRAINT `fk_ops_cargo_hs` FOREIGN KEY (`hs_code`) REFERENCES `hs_codes` (`code`) ON DELETE SET NULL,
    CONSTRAINT `fk_ops_cargo_operator` FOREIGN KEY (`assigned_operator_id`) REFERENCES `ops_operator_profiles` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabel: ops_cargo_items (Rincian Fisik Koli / Packing List per Booking)
DROP TABLE IF EXISTS `ops_cargo_items`;
CREATE TABLE `ops_cargo_items` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `cargo_booking_id` CHAR(36) NOT NULL,
    `item_name` VARCHAR(255) NOT NULL,
    `hs_code` VARCHAR(15) NULL,
    `package_type` VARCHAR(50) NOT NULL DEFAULT 'CARTON',            -- CARTON, PALLET, WOODEN_CRATE, DRUM, BUNDLE
    `quantity` INT NOT NULL DEFAULT 1,
    `weight_kg` DECIMAL(10, 2) NOT NULL,
    `length_cm` DECIMAL(8, 2) NOT NULL DEFAULT 0.00,
    `width_cm` DECIMAL(8, 2) NOT NULL DEFAULT 0.00,
    `height_cm` DECIMAL(8, 2) NOT NULL DEFAULT 0.00,
    `volume_cbm` DECIMAL(10, 4) NOT NULL DEFAULT 0.0000,
    `is_dangerous_good` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ops_cargo_items_booking` (`cargo_booking_id`),
    KEY `idx_ops_cargo_items_hs` (`hs_code`),
    CONSTRAINT `fk_ops_cargo_items_booking` FOREIGN KEY (`cargo_booking_id`) REFERENCES `ops_cargo_bookings` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ops_cargo_items_hs` FOREIGN KEY (`hs_code`) REFERENCES `hs_codes` (`code`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabel: ops_manifests
DROP TABLE IF EXISTS `ops_manifests`;
CREATE TABLE `ops_manifests` (
    `id` CHAR(36) NOT NULL,
    `manifest_number` VARCHAR(50) NOT NULL,                     -- MMK-JKT-SUB-20261106
    `origin_port_id` VARCHAR(10) NOT NULL,
    `destination_port_id` VARCHAR(10) NOT NULL,
    `vessel_or_vehicle_name` VARCHAR(255) NOT NULL,             -- KM Sora Bahari 08
    `voyage_number` VARCHAR(50) NOT NULL,
    `departure_eta` TIMESTAMP NOT NULL,
    `arrival_eta` TIMESTAMP NOT NULL,
    `status` VARCHAR(50) NOT NULL DEFAULT 'PREPARING',          -- PREPARING, LOCKED_SEALED, SAILING, ARRIVED, UNLOADED
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `ops_manifest_number_unique` (`manifest_number`),
    KEY `idx_ops_manifest_origin` (`origin_port_id`),
    KEY `idx_ops_manifest_destination` (`destination_port_id`),
    CONSTRAINT `fk_ops_manifest_origin` FOREIGN KEY (`origin_port_id`) REFERENCES `ports` (`id`),
    CONSTRAINT `fk_ops_manifest_dest` FOREIGN KEY (`destination_port_id`) REFERENCES `ports` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabel: ops_manifest_items
DROP TABLE IF EXISTS `ops_manifest_items`;
CREATE TABLE `ops_manifest_items` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `manifest_id` CHAR(36) NOT NULL,
    `cargo_booking_id` CHAR(36) NOT NULL,
    `container_serial_number` VARCHAR(20) NOT NULL,             -- SRAU-728192-0 (ISO 6346)
    `container_type` VARCHAR(50) NOT NULL DEFAULT 'DRY_20FT',   -- DRY_20FT, DRY_40FT, REEFER_40HC
    `seal_number` VARCHAR(50) NOT NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ops_manifest_items_manifest` (`manifest_id`),
    KEY `idx_ops_manifest_items_cargo` (`cargo_booking_id`),
    KEY `idx_ops_manifest_items_container` (`container_serial_number`),
    CONSTRAINT `fk_ops_manifest_items_manifest` FOREIGN KEY (`manifest_id`) REFERENCES `ops_manifests` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ops_manifest_items_cargo` FOREIGN KEY (`cargo_booking_id`) REFERENCES `ops_cargo_bookings` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabel: ops_tracking_events
DROP TABLE IF EXISTS `ops_tracking_events`;
CREATE TABLE `ops_tracking_events` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `cargo_booking_id` CHAR(36) NOT NULL,
    `event_name` VARCHAR(255) NOT NULL,
    `current_status` VARCHAR(50) NOT NULL,
    `latitude` DECIMAL(10, 7) NOT NULL,
    `longitude` DECIMAL(10, 7) NOT NULL,
    `operator_id` CHAR(36) NULL,
    `event_timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ops_tracking_cargo` (`cargo_booking_id`),
    KEY `idx_ops_tracking_time` (`event_timestamp`),
    CONSTRAINT `fk_ops_tracking_cargo` FOREIGN KEY (`cargo_booking_id`) REFERENCES `ops_cargo_bookings` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ops_tracking_operator` FOREIGN KEY (`operator_id`) REFERENCES `ops_operator_profiles` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabel: ops_incident_escalations
DROP TABLE IF EXISTS `ops_incident_escalations`;
CREATE TABLE `ops_incident_escalations` (
    `id` CHAR(36) NOT NULL,
    `incident_ticket_no` VARCHAR(50) NOT NULL,                  -- ESC-20261106-004
    `cargo_booking_id` CHAR(36) NOT NULL,
    `reporter_operator_id` CHAR(36) NOT NULL,
    `incident_type` VARCHAR(50) NOT NULL,                       -- FIELD_COST, DAMAGE_CLAIM, SLA_BREACH, WEATHER_HOLD
    `requested_amount` DECIMAL(14, 2) NULL,
    `bap_document_path` VARCHAR(255) NULL,
    `approval_status` VARCHAR(50) NOT NULL DEFAULT 'PENDING_REVIEW', -- PENDING_REVIEW, APPROVED_BY_ADMIN, REJECTED, INSURANCE_PROCESSED
    `admin_notes` TEXT NULL,
    `sla_response_minutes` INT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `ops_incident_ticket_unique` (`incident_ticket_no`),
    KEY `idx_ops_incident_cargo` (`cargo_booking_id`),
    KEY `idx_ops_incident_operator` (`reporter_operator_id`),
    CONSTRAINT `fk_ops_incident_cargo` FOREIGN KEY (`cargo_booking_id`) REFERENCES `ops_cargo_bookings` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ops_incident_operator` FOREIGN KEY (`reporter_operator_id`) REFERENCES `ops_operator_profiles` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabel: ops_epods (Electronic Proof of Delivery - Serah Terima Barang)
DROP TABLE IF EXISTS `ops_epods`;
CREATE TABLE `ops_epods` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `cargo_booking_id` CHAR(36) NOT NULL,
    `recipient_name` VARCHAR(255) NOT NULL,
    `recipient_phone` VARCHAR(50) NOT NULL,
    `signature_image_path` VARCHAR(255) NULL,
    `photo_proof_path` VARCHAR(255) NULL,
    `latitude` DECIMAL(10, 7) NOT NULL,
    `longitude` DECIMAL(10, 7) NOT NULL,
    `confirmed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `ops_epods_cargo_unique` (`cargo_booking_id`),
    CONSTRAINT `fk_ops_epods_cargo` FOREIGN KEY (`cargo_booking_id`) REFERENCES `ops_cargo_bookings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabel: ops_fleet_telemetry (Sensor GPS Hardware IoT Kapal & Truk)
DROP TABLE IF EXISTS `ops_fleet_telemetry`;
CREATE TABLE `ops_fleet_telemetry` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `manifest_id` CHAR(36) NOT NULL,
    `device_id` VARCHAR(100) NOT NULL,
    `latitude` DECIMAL(10, 7) NOT NULL,
    `longitude` DECIMAL(10, 7) NOT NULL,
    `speed_knots` DECIMAL(6, 2) NOT NULL DEFAULT 0.00,
    `heading_degrees` DECIMAL(5, 2) NOT NULL DEFAULT 0.00,
    `recorded_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ops_telemetry_manifest` (`manifest_id`),
    KEY `idx_ops_telemetry_time` (`recorded_at`),
    CONSTRAINT `fk_ops_telemetry_manifest` FOREIGN KEY (`manifest_id`) REFERENCES `ops_manifests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -------------------------------------------------------------------------
-- 3. GOVERNANCE & FINANCIAL MODULE (Eks Skema admin)
-- -------------------------------------------------------------------------

-- Tabel: adm_corporate_credits
DROP TABLE IF EXISTS `adm_corporate_credits`;
CREATE TABLE `adm_corporate_credits` (
    `id` CHAR(36) NOT NULL,
    `company_id` CHAR(36) NOT NULL,
    `credit_limit_amount` DECIMAL(16, 2) NOT NULL DEFAULT 0.00,
    `used_credit_amount` DECIMAL(16, 2) NOT NULL DEFAULT 0.00,
    `top_days` INT NOT NULL DEFAULT 30,                          -- 30, 45, 60 hari
    `kyc_status` VARCHAR(50) NOT NULL DEFAULT 'PENDING_VERIFICATION', -- PENDING_VERIFICATION, VERIFIED_APPROVED, REJECTED
    `bank_guarantee_ref` VARCHAR(100) NULL,
    `debt_enforcement_stage` VARCHAR(50) NOT NULL DEFAULT 'HEALTHY',  -- HEALTHY, SP1_ISSUED, SP2_LOCKED, SP3_LEGAL_COLLECTION
    `verified_by_admin_id` CHAR(36) NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `adm_credit_company_unique` (`company_id`),
    KEY `idx_adm_credit_admin` (`verified_by_admin_id`),
    CONSTRAINT `fk_adm_credit_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_adm_credit_admin` FOREIGN KEY (`verified_by_admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabel: adm_escrow_ledgers
DROP TABLE IF EXISTS `adm_escrow_ledgers`;
CREATE TABLE `adm_escrow_ledgers` (
    `id` CHAR(36) NOT NULL,
    `cargo_booking_id` CHAR(36) NOT NULL,
    `total_paid_amount` DECIMAL(16, 2) NOT NULL,
    `milestone_1_pickup_amount` DECIMAL(16, 2) NOT NULL,        -- 30%
    `milestone_1_released_at` TIMESTAMP NULL DEFAULT NULL,
    `milestone_2_sailing_amount` DECIMAL(16, 2) NOT NULL,       -- 40%
    `milestone_2_released_at` TIMESTAMP NULL DEFAULT NULL,
    `milestone_3_delivered_amount` DECIMAL(16, 2) NOT NULL,     -- 30%
    `milestone_3_released_at` TIMESTAMP NULL DEFAULT NULL,
    `escrow_status` VARCHAR(50) NOT NULL DEFAULT 'HELD_IN_ESCROW', -- HELD_IN_ESCROW, PARTIALLY_RELEASED, FULLY_SETTLED, DISPUTE_FROZEN
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `adm_escrow_cargo_unique` (`cargo_booking_id`),
    CONSTRAINT `fk_adm_escrow_cargo` FOREIGN KEY (`cargo_booking_id`) REFERENCES `ops_cargo_bookings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabel: adm_revenue_splits
DROP TABLE IF EXISTS `adm_revenue_splits`;
CREATE TABLE `adm_revenue_splits` (
    `id` CHAR(36) NOT NULL,
    `cargo_booking_id` CHAR(36) NOT NULL,
    `platform_fee_amount` DECIMAL(14, 2) NOT NULL,              -- 10-15%
    `operator_share_amount` DECIMAL(14, 2) NOT NULL,            -- 60-80%
    `tax_and_port_dues` DECIMAL(14, 2) NOT NULL,
    `net_platform_profit` DECIMAL(14, 2) NOT NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `adm_split_cargo_unique` (`cargo_booking_id`),
    CONSTRAINT `fk_adm_split_cargo` FOREIGN KEY (`cargo_booking_id`) REFERENCES `ops_cargo_bookings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabel: adm_invoices
DROP TABLE IF EXISTS `adm_invoices`;
CREATE TABLE `adm_invoices` (
    `id` CHAR(36) NOT NULL,
    `invoice_number` VARCHAR(50) NOT NULL,                      -- INV-B2B-20261106-008
    `company_id` CHAR(36) NOT NULL,
    `cargo_booking_id` CHAR(36) NULL,
    `total_invoice_amount` DECIMAL(16, 2) NOT NULL,
    `currency` VARCHAR(10) NOT NULL DEFAULT 'IDR',
    `exchange_rate_locked` DECIMAL(14, 4) NOT NULL DEFAULT 1.0000,
    `due_date` DATE NOT NULL,
    `payment_status` VARCHAR(50) NOT NULL DEFAULT 'UNPAID',     -- UNPAID, PARTIALLY_PAID, OVERDUE, PAID_SETTLED
    `paid_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `adm_invoice_number_unique` (`invoice_number`),
    KEY `idx_adm_invoices_company` (`company_id`),
    KEY `idx_adm_invoices_cargo` (`cargo_booking_id`),
    KEY `idx_adm_invoices_due` (`due_date`, `payment_status`),
    CONSTRAINT `fk_adm_invoices_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_adm_invoices_cargo` FOREIGN KEY (`cargo_booking_id`) REFERENCES `ops_cargo_bookings` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabel: payment_transactions (Mutasi Pembayaran Riil & Rekonsiliasi Gateway)
DROP TABLE IF EXISTS `payment_transactions`;
CREATE TABLE `payment_transactions` (
    `id` CHAR(36) NOT NULL,
    `invoice_id` CHAR(36) NOT NULL,
    `payer_user_id` CHAR(36) NOT NULL,
    `payment_gateway` VARCHAR(50) NOT NULL DEFAULT 'MANUAL_TRANSFER', -- MIDTRANS, XENDIT, MANUAL_TRANSFER
    `payment_channel` VARCHAR(50) NOT NULL,                          -- BCA_VA, MANDIRI_VA, BNI_VA, QRIS, BANK_TRANSFER
    `transaction_ref` VARCHAR(100) NOT NULL,                         -- Gateway Order ID / Settlement Reference
    `amount` DECIMAL(16, 2) NOT NULL,
    `payment_status` VARCHAR(50) NOT NULL DEFAULT 'PENDING',         -- PENDING, SETTLEMENT, EXPIRED, FAILED, REFUNDED
    `raw_gateway_payload` JSON NULL,
    `paid_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `payment_tx_ref_unique` (`transaction_ref`),
    KEY `idx_payment_tx_invoice` (`invoice_id`),
    KEY `idx_payment_tx_payer` (`payer_user_id`),
    KEY `idx_payment_tx_status` (`payment_status`),
    CONSTRAINT `fk_payment_tx_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `adm_invoices` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_payment_tx_payer` FOREIGN KEY (`payer_user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabel: adm_operator_invitations
DROP TABLE IF EXISTS `adm_operator_invitations`;
CREATE TABLE `adm_operator_invitations` (
    `id` CHAR(36) NOT NULL,
    `invitation_token` VARCHAR(64) NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `phone` VARCHAR(50) NOT NULL,
    `assigned_hub_id` VARCHAR(10) NOT NULL,
    `assigned_role` VARCHAR(50) NOT NULL DEFAULT 'OPERATOR_DOMESTIC', -- OPERATOR_DOMESTIC, OPERATOR_INTERNATIONAL
    `expires_at` TIMESTAMP NOT NULL,                            -- 24 jam
    `is_claimed` TINYINT(1) NOT NULL DEFAULT 0,
    `invited_by_admin_id` CHAR(36) NOT NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `adm_invitation_token_unique` (`invitation_token`),
    KEY `idx_adm_invitation_hub` (`assigned_hub_id`),
    KEY `idx_adm_invitation_admin` (`invited_by_admin_id`),
    CONSTRAINT `fk_adm_invitation_hub` FOREIGN KEY (`assigned_hub_id`) REFERENCES `ports` (`id`),
    CONSTRAINT `fk_adm_invitation_admin` FOREIGN KEY (`invited_by_admin_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabel: adm_gateway_sessions
DROP TABLE IF EXISTS `adm_gateway_sessions`;
CREATE TABLE `adm_gateway_sessions` (
    `id` VARCHAR(50) NOT NULL,                                  -- wa_gateway_session_01
    `channel_type` VARCHAR(50) NOT NULL,                        -- WHATSAPP_BAILEYS, EMAIL_SMTP
    `connection_status` VARCHAR(50) NOT NULL DEFAULT 'DISCONNECTED', -- CONNECTED, PAIRING_REQUIRED, DISCONNECTED, RATE_LIMITED
    `paired_phone_number` VARCHAR(50) NULL,
    `remaining_quota_daily` INT NOT NULL DEFAULT 5000,
    `last_ping_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabel: adm_system_settings
DROP TABLE IF EXISTS `adm_system_settings`;
CREATE TABLE `adm_system_settings` (
    `key` VARCHAR(100) NOT NULL,
    `value` LONGTEXT NOT NULL,
    `group_name` VARCHAR(50) NOT NULL,                          -- ai_engine, gateways, financial, system
    `description` TEXT NULL,
    `updated_by` CHAR(36) NULL,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`key`),
    KEY `idx_adm_settings_group` (`group_name`),
    KEY `idx_adm_settings_admin` (`updated_by`),
    CONSTRAINT `fk_adm_settings_admin` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabel: adm_immutable_audit_trails
DROP TABLE IF EXISTS `adm_immutable_audit_trails`;
CREATE TABLE `adm_immutable_audit_trails` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `admin_user_id` CHAR(36) NOT NULL,
    `action_type` VARCHAR(100) NOT NULL,                        -- APPROVE_KYC, RELEASE_ESCROW_M2, TRIGGER_WEATHER_HOLD
    `target_schema` VARCHAR(50) NOT NULL DEFAULT 'default',
    `target_table` VARCHAR(100) NOT NULL,
    `record_id` VARCHAR(100) NOT NULL,
    `before_state_json` JSON NULL,
    `after_state_json` JSON NULL,
    `ip_address` VARCHAR(50) NOT NULL,
    `user_agent` TEXT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_adm_audit_admin` (`admin_user_id`),
    KEY `idx_adm_audit_time` (`created_at`),
    CONSTRAINT `fk_adm_audit_admin` FOREIGN KEY (`admin_user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -------------------------------------------------------------------------
-- 4. LARAVEL FRAMEWORK INFRASTRUCTURE TABLES (Auth, Notifications, Cache, Jobs)
-- -------------------------------------------------------------------------

DROP TABLE IF EXISTS `password_reset_tokens`;
CREATE TABLE `password_reset_tokens` (
    `email` VARCHAR(255) NOT NULL,
    `token` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
    `id` CHAR(36) NOT NULL,
    `type` VARCHAR(255) NOT NULL,
    `notifiable_type` VARCHAR(255) NOT NULL,
    `notifiable_id` CHAR(36) NOT NULL,
    `data` TEXT NOT NULL,
    `read_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `notifications_notifiable_type_notifiable_id_index` (`notifiable_type`, `notifiable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `sessions`;
CREATE TABLE `sessions` (
    `id` VARCHAR(255) NOT NULL,
    `user_id` CHAR(36) NULL,
    `ip_address` VARCHAR(45) NULL,
    `user_agent` TEXT NULL,
    `payload` LONGTEXT NOT NULL,
    `last_activity` INT NOT NULL,
    PRIMARY KEY (`id`),
    KEY `sessions_user_id_index` (`user_id`),
    KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `cache`;
CREATE TABLE `cache` (
    `key` VARCHAR(255) NOT NULL,
    `value` MEDIUMTEXT NOT NULL,
    `expiration` INT NOT NULL,
    PRIMARY KEY (`key`),
    KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `cache_locks`;
CREATE TABLE `cache_locks` (
    `key` VARCHAR(255) NOT NULL,
    `owner` VARCHAR(255) NOT NULL,
    `expiration` INT NOT NULL,
    PRIMARY KEY (`key`),
    KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `jobs`;
CREATE TABLE `jobs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `queue` VARCHAR(255) NOT NULL,
    `payload` LONGTEXT NOT NULL,
    `attempts` TINYINT UNSIGNED NOT NULL,
    `reserved_at` INT UNSIGNED NULL,
    `available_at` INT UNSIGNED NOT NULL,
    `created_at` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`id`),
    KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `job_batches`;
CREATE TABLE `job_batches` (
    `id` VARCHAR(255) NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `total_jobs` INT NOT NULL,
    `pending_jobs` INT NOT NULL,
    `failed_jobs` INT NOT NULL,
    `failed_job_ids` LONGTEXT NOT NULL,
    `options` MEDIUMTEXT NULL,
    `cancelled_at` INT NULL,
    `created_at` INT NOT NULL,
    `finished_at` INT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `failed_jobs`;
CREATE TABLE `failed_jobs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `uuid` VARCHAR(255) NOT NULL,
    `connection` TEXT NOT NULL,
    `queue` TEXT NOT NULL,
    `payload` LONGTEXT NOT NULL,
    `exception` LONGTEXT NOT NULL,
    `failed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- 5. INITIAL SEED DATA FOR FAST ACCESS (Admin, Operator, Customer)
-- Password for all seed users: "password"
-- -------------------------------------------------------------------------
INSERT INTO `users` (`id`, `name`, `username`, `email`, `phone`, `password`, `user_type`, `admin_role`, `status`, `kyc_aml_status`) VALUES
('019445a1-0001-7000-8000-000000000001', 'Super Admin', 'admin', 'admin@fastcargo.internal', '+62811000001', '$2y$12$Ab5uVd7WlnrtcFyNbZXHked6DwIV8AbcHJYaGmBHKwFqsuKCwHP6u', 'ADMIN_USER', 'SUPER_ADMIN', 'ACTIVE', 'VERIFIED'),
('019445a1-0002-7000-8000-000000000002', 'Head Operator', 'operator', 'operator@fastcargo.com', '+62811000002', '$2y$12$Ab5uVd7WlnrtcFyNbZXHked6DwIV8AbcHJYaGmBHKwFqsuKCwHP6u', 'OPERATOR_USER', NULL, 'ACTIVE', 'VERIFIED'),
('019445a1-0003-7000-8000-000000000003', 'Horizon Customer', 'horizon', 'mail@simmmple.com', '+62811000003', '$2y$12$Ab5uVd7WlnrtcFyNbZXHked6DwIV8AbcHJYaGmBHKwFqsuKCwHP6u', 'RETAIL_CUSTOMER', NULL, 'ACTIVE', 'VERIFIED')
ON DUPLICATE KEY UPDATE `email` = VALUES(`email`);

SET FOREIGN_KEY_CHECKS = 1;
COMMIT;
