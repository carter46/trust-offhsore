<?php
/**
 * Ensure all core tables exist.
 * This is the bootstrap migration — it creates every table
 * that the application expects if the database is empty or partial.
 */

return [
    'id' => '2026_09_03_ensure_all_tables',
    'description' => 'Create all core tables if they do not exist',
    'up' => function (mysqli $conn) {

        // --- admin_users ---
        DatabaseAutoMigrate::execOrFail($conn, "CREATE TABLE IF NOT EXISTS `admin_users` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `username` varchar(50) NOT NULL,
            `password_hash` varchar(255) NOT NULL,
            `email` varchar(100) DEFAULT NULL,
            `last_login` datetime DEFAULT NULL,
            `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `username` (`username`),
            KEY `idx_username` (`username`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", 'Create admin_users');

        // --- cookie_preferences ---
        DatabaseAutoMigrate::execOrFail($conn, "CREATE TABLE IF NOT EXISTS `cookie_preferences` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `session_id` varchar(128) NOT NULL,
            `preferences` text DEFAULT NULL,
            `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_session_id` (`session_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", 'Create cookie_preferences');

        // --- pages ---
        DatabaseAutoMigrate::execOrFail($conn, "CREATE TABLE IF NOT EXISTS `pages` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `page_slug` varchar(100) NOT NULL,
            `page_title` varchar(255) NOT NULL,
            `content` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
            `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
            `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `page_slug` (`page_slug`),
            KEY `idx_page_slug` (`page_slug`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", 'Create pages');

        // --- settings ---
        DatabaseAutoMigrate::execOrFail($conn, "CREATE TABLE IF NOT EXISTS `settings` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `setting_key` varchar(100) NOT NULL,
            `setting_value` text DEFAULT NULL,
            `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `setting_key` (`setting_key`),
            KEY `idx_setting_key` (`setting_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", 'Create settings');

        // --- shipments ---
        DatabaseAutoMigrate::execOrFail($conn, "CREATE TABLE IF NOT EXISTS `shipments` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `tracking_number` varchar(20) NOT NULL,
            `sender_name` varchar(255) NOT NULL,
            `sender_address` text NOT NULL,
            `sender_city` varchar(100) DEFAULT NULL,
            `sender_state` varchar(50) DEFAULT NULL,
            `sender_zip` varchar(20) DEFAULT NULL,
            `sender_country` varchar(50) DEFAULT 'United States',
            `sender_email` varchar(255) DEFAULT NULL,
            `sender_phone` varchar(50) DEFAULT NULL,
            `recipient_name` varchar(255) NOT NULL,
            `recipient_address` text NOT NULL,
            `recipient_city` varchar(100) DEFAULT NULL,
            `recipient_state` varchar(50) DEFAULT NULL,
            `recipient_zip` varchar(20) DEFAULT NULL,
            `recipient_country` varchar(50) DEFAULT 'United States',
            `recipient_email` varchar(255) DEFAULT NULL,
            `recipient_phone` varchar(50) DEFAULT NULL,
            `weight` decimal(10,2) DEFAULT NULL,
            `dimensions` varchar(50) DEFAULT NULL,
            `service_type` varchar(100) NOT NULL,
            `status` varchar(50) NOT NULL DEFAULT 'Label Created',
            `estimated_delivery` datetime DEFAULT NULL,
            `reference_number` varchar(100) DEFAULT NULL,
            `sender_latitude` decimal(10,8) DEFAULT NULL,
            `sender_longitude` decimal(11,8) DEFAULT NULL,
            `recipient_latitude` decimal(10,8) DEFAULT NULL,
            `recipient_longitude` decimal(11,8) DEFAULT NULL,
            `pickup_location` varchar(255) DEFAULT NULL,
            `pickup_latitude` decimal(10,8) DEFAULT NULL,
            `pickup_longitude` decimal(11,8) DEFAULT NULL,
            `dropoff_location` varchar(255) DEFAULT NULL,
            `dropoff_latitude` decimal(10,8) DEFAULT NULL,
            `dropoff_longitude` decimal(11,8) DEFAULT NULL,
            `shipment_worth` decimal(10,2) DEFAULT NULL,
            `base_cost` decimal(10,2) DEFAULT NULL,
            `clearance_cost` decimal(10,2) DEFAULT NULL,
            `total_cost` decimal(10,2) DEFAULT NULL,
            `item_image` varchar(255) DEFAULT NULL,
            `shipment_created_at` datetime DEFAULT NULL,
            `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
            `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `tracking_number` (`tracking_number`),
            KEY `idx_tracking_number` (`tracking_number`),
            KEY `idx_status` (`status`),
            KEY `idx_created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", 'Create shipments');

        // --- tracking_events ---
        DatabaseAutoMigrate::execOrFail($conn, "CREATE TABLE IF NOT EXISTS `tracking_events` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `shipment_id` int(11) NOT NULL,
            `event_type` varchar(50) NOT NULL,
            `description` text NOT NULL,
            `location` varchar(255) DEFAULT NULL,
            `latitude` decimal(10,8) DEFAULT NULL,
            `longitude` decimal(11,8) DEFAULT NULL,
            `event_date` datetime NOT NULL,
            `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_shipment_id` (`shipment_id`),
            KEY `idx_event_date` (`event_date`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", 'Create tracking_events');

        // FK: only add if not already present
        $fk = $conn->query(
            "SELECT COUNT(*) AS cnt
             FROM information_schema.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE()
               AND TABLE_NAME = 'tracking_events'
               AND CONSTRAINT_TYPE = 'FOREIGN KEY'
               AND CONSTRAINT_NAME = 'tracking_events_ibfk_1'"
        );
        $fkRow = $fk ? $fk->fetch_assoc() : null;
        if ($fkRow && empty($fkRow['cnt'])) {
            $conn->query("ALTER TABLE `tracking_events` ADD CONSTRAINT `tracking_events_ibfk_1` FOREIGN KEY (`shipment_id`) REFERENCES `shipments` (`id`) ON DELETE CASCADE");
        }
    },
];
