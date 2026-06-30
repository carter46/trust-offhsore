-- Store estimated delivery with time (not date-only)
ALTER TABLE `shipments`
  MODIFY COLUMN `estimated_delivery` datetime DEFAULT NULL;
