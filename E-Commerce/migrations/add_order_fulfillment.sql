-- Sipariş kargo / teslim takibi

ALTER TABLE orders
  ADD COLUMN tracking_number VARCHAR(64) NULL,
  ADD COLUMN carrier VARCHAR(64) NULL,
  ADD COLUMN shipped_at DATETIME NULL,
  ADD COLUMN delivered_at DATETIME NULL;
