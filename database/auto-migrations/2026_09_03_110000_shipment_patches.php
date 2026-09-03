<?php
/**
 * Patch columns that were added after the initial SQL dump:
 * - clearance_cost (decimal)
 * - shipment_created_at (datetime)
 * - estimated_delivery DATE → DATETIME
 */

return [
    'id' => '2026_09_03_shipment_patches',
    'description' => 'Add clearance_cost, shipment_created_at, widen estimated_delivery to DATETIME',
    'up' => function (mysqli $conn) {

        // clearance_cost
        DatabaseAutoMigrate::ensureColumn(
            $conn, 'shipments', 'clearance_cost',
            '`clearance_cost` decimal(10,2) DEFAULT NULL AFTER `base_cost`'
        );

        // shipment_created_at
        DatabaseAutoMigrate::ensureColumn(
            $conn, 'shipments', 'shipment_created_at',
            '`shipment_created_at` datetime DEFAULT NULL AFTER `item_image`'
        );

        // estimated_delivery: widen DATE → DATETIME (idempotent; MODIFY is a no-op if already DATETIME)
        DatabaseAutoMigrate::modifyColumn(
            $conn, 'shipments', 'estimated_delivery',
            '`estimated_delivery` datetime DEFAULT NULL'
        );
    },
];
