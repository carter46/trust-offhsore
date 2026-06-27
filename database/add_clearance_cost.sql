-- Run once on your database (phpMyAdmin / Hostinger)
ALTER TABLE `shipments`
  ADD COLUMN `clearance_cost` decimal(10,2) DEFAULT NULL AFTER `base_cost`;

-- Optional: set total_cost = shipping + clearance for existing rows
UPDATE `shipments`
SET `total_cost` = IFNULL(`base_cost`, 0) + IFNULL(`clearance_cost`, 0)
WHERE `base_cost` IS NOT NULL OR `clearance_cost` IS NOT NULL;
