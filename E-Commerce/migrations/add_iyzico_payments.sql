-- iyzico ödeme entegrasyonu için şema güncellemesi
-- Mevcut siparişler payment_status='paid' olarak işaretlenir (eski simüle checkout).

ALTER TABLE orders
  ADD COLUMN payment_status VARCHAR(32) NOT NULL DEFAULT 'paid',
  ADD COLUMN payment_provider VARCHAR(32) NULL,
  ADD COLUMN shipping_json TEXT NULL;

CREATE TABLE IF NOT EXISTS payments (
  payment_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id INT UNSIGNED NOT NULL,
  provider VARCHAR(32) NOT NULL DEFAULT 'iyzico',
  conversation_id VARCHAR(64) NOT NULL,
  token VARCHAR(255) NULL,
  status VARCHAR(32) NOT NULL DEFAULT 'pending',
  amount DECIMAL(12, 2) NOT NULL,
  currency VARCHAR(3) NOT NULL DEFAULT 'TRY',
  raw_response JSON NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (payment_id),
  KEY idx_payments_order (order_id),
  KEY idx_payments_token (token),
  KEY idx_payments_conversation (conversation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
