-- Editable shipment creation date (defaults to row created_at for existing rows)
ALTER TABLE `shipments`
  ADD COLUMN `shipment_created_at` datetime DEFAULT NULL AFTER `estimated_delivery`;

UPDATE `shipments`
SET `shipment_created_at` = `created_at`
WHERE `shipment_created_at` IS NULL;
