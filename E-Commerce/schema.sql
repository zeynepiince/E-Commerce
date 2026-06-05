-- ZERA E-Commerce — full database schema (fresh install)
-- Usage:
--   mysql -u root -p -e "CREATE DATABASE chatbotv2_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
--   mysql -u root -p chatbotv2_db < E-Commerce/schema.sql
--
-- After schema:
--   1. composer install (iyzico SDK)
--   2. import_products.php?source=dummy&limit=100  (requires IMPORT_PRODUCTS_ENABLED=true + admin)
--   3. import_products.php?women=1                 (women's clothing catalog)
--   4. migrations/seed_womens_clothing.sql         (women's t-shirt seed)
--
-- Wishlist/favorites are browser localStorage only (no user_favorites table).

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------------
-- users
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
  user_id INT NOT NULL AUTO_INCREMENT,
  full_name VARCHAR(255) NOT NULL,
  email VARCHAR(255) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  google_id VARCHAR(255) NULL DEFAULT NULL,
  facebook_id VARCHAR(255) NULL DEFAULT NULL,
  phone VARCHAR(30) NULL DEFAULT NULL,
  address TEXT NULL,
  newsletter_opt_in TINYINT(1) NOT NULL DEFAULT 0,
  email_notifications TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id),
  UNIQUE KEY uq_users_email (email),
  KEY idx_users_google_id (google_id),
  KEY idx_users_facebook_id (facebook_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- categories
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS categories (
  category_id INT NOT NULL AUTO_INCREMENT,
  category_name VARCHAR(100) NOT NULL,
  PRIMARY KEY (category_id),
  UNIQUE KEY uq_categories_name (category_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO categories (category_id, category_name) VALUES
  (1, 'Electronics'),
  (2, 'Fashion'),
  (3, 'Home'),
  (4, 'men''s clothing'),
  (5, 'jewelery'),
  (6, 'women''s clothing')
ON DUPLICATE KEY UPDATE category_name = VALUES(category_name);

-- ---------------------------------------------------------------------------
-- products
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS products (
  product_id INT NOT NULL AUTO_INCREMENT,
  external_id VARCHAR(100) NULL DEFAULT NULL,
  name VARCHAR(120) NOT NULL,
  price DECIMAL(10,2) NOT NULL,
  image_url TEXT NULL,
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  badges JSON NULL COMMENT 'Product labels: ["hizli-teslimat","en-cok-satan"]',
  category_id INT NULL DEFAULT NULL,
  sub_category VARCHAR(64) NULL DEFAULT NULL,
  description TEXT NULL,
  stock_quantity INT NOT NULL DEFAULT 0,
  sizes VARCHAR(255) NULL DEFAULT NULL COMMENT 'Comma-separated sizes, e.g. S,M,L',
  PRIMARY KEY (product_id),
  UNIQUE KEY uq_products_external_id (external_id),
  KEY idx_products_category (category_id),
  KEY idx_products_sub_category (sub_category),
  CONSTRAINT fk_products_category
    FOREIGN KEY (category_id) REFERENCES categories (category_id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- orders
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS orders (
  order_id INT NOT NULL AUTO_INCREMENT,
  user_id INT NOT NULL,
  total_amount DECIMAL(10,2) NOT NULL,
  status VARCHAR(32) NOT NULL DEFAULT 'pending',
  payment_method VARCHAR(50) NULL DEFAULT NULL,
  payment_status VARCHAR(32) NOT NULL DEFAULT 'awaiting_payment',
  payment_provider VARCHAR(32) NULL DEFAULT NULL,
  shipping_json TEXT NULL,
  tracking_number VARCHAR(64) NULL DEFAULT NULL,
  carrier VARCHAR(64) NULL DEFAULT NULL,
  shipped_at DATETIME NULL DEFAULT NULL,
  delivered_at DATETIME NULL DEFAULT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (order_id),
  KEY idx_orders_user (user_id),
  KEY idx_orders_status (status),
  KEY idx_orders_payment_status (payment_status),
  CONSTRAINT fk_orders_user
    FOREIGN KEY (user_id) REFERENCES users (user_id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- order_items
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS order_items (
  order_item_id INT NOT NULL AUTO_INCREMENT,
  order_id INT NOT NULL,
  product_id INT NULL DEFAULT NULL,
  quantity INT NOT NULL,
  unit_price DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (order_item_id),
  KEY idx_order_items_order (order_id),
  KEY idx_order_items_product (product_id),
  CONSTRAINT fk_order_items_order
    FOREIGN KEY (order_id) REFERENCES orders (order_id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_order_items_product
    FOREIGN KEY (product_id) REFERENCES products (product_id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- payments (iyzico log)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS payments (
  payment_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id INT UNSIGNED NOT NULL,
  provider VARCHAR(32) NOT NULL DEFAULT 'iyzico',
  conversation_id VARCHAR(64) NOT NULL,
  token VARCHAR(255) NULL DEFAULT NULL,
  status VARCHAR(32) NOT NULL DEFAULT 'pending',
  amount DECIMAL(12,2) NOT NULL,
  currency VARCHAR(3) NOT NULL DEFAULT 'TRY',
  raw_response JSON NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (payment_id),
  KEY idx_payments_order (order_id),
  KEY idx_payments_token (token),
  KEY idx_payments_conversation (conversation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- password_resets
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS password_resets (
  reset_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (reset_id),
  KEY idx_password_resets_token (token_hash),
  KEY idx_password_resets_user (user_id),
  KEY idx_password_resets_expires (expires_at),
  CONSTRAINT fk_password_resets_user
    FOREIGN KEY (user_id) REFERENCES users (user_id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- support_interactions (logged-in chat log)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS support_interactions (
  interaction_id INT NOT NULL AUTO_INCREMENT,
  user_id INT NOT NULL,
  message TEXT NOT NULL,
  sender ENUM('user', 'bot') NOT NULL,
  intent VARCHAR(50) NULL DEFAULT NULL,
  recommended_product_id INT NULL DEFAULT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (interaction_id),
  KEY idx_support_interactions_user (user_id),
  KEY idx_support_interactions_product (recommended_product_id),
  CONSTRAINT fk_support_interactions_user
    FOREIGN KEY (user_id) REFERENCES users (user_id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_support_interactions_product
    FOREIGN KEY (recommended_product_id) REFERENCES products (product_id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT chk_support_interactions_sender CHECK (sender IN ('user', 'bot'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- chatbot_feedback (thumbs up/down)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS chatbot_feedback (
  feedback_id INT NOT NULL AUTO_INCREMENT,
  user_id INT NULL DEFAULT NULL,
  helpful TINYINT(1) NOT NULL,
  intent VARCHAR(50) NULL DEFAULT NULL,
  source VARCHAR(50) NULL DEFAULT NULL,
  confidence DECIMAL(4,2) NULL DEFAULT NULL,
  used_ai TINYINT(1) NOT NULL DEFAULT 0,
  escalated_to_human TINYINT(1) NOT NULL DEFAULT 0,
  experiment_mode VARCHAR(20) NULL DEFAULT NULL,
  experiment_bucket VARCHAR(20) NULL DEFAULT NULL,
  experiment_variants JSON NULL,
  user_message TEXT NULL,
  bot_reply TEXT NULL,
  page VARCHAR(255) NULL DEFAULT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (feedback_id),
  KEY idx_chatbot_feedback_intent (intent),
  KEY idx_chatbot_feedback_created (created_at),
  KEY idx_chatbot_feedback_user (user_id),
  CONSTRAINT fk_chatbot_feedback_user
    FOREIGN KEY (user_id) REFERENCES users (user_id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- newsletter_subscribers
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS newsletter_subscribers (
  subscriber_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  email VARCHAR(255) NOT NULL,
  user_id INT NULL DEFAULT NULL,
  source VARCHAR(50) NOT NULL DEFAULT 'homepage',
  subscribed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (subscriber_id),
  UNIQUE KEY uq_newsletter_email (email),
  KEY idx_newsletter_user (user_id),
  CONSTRAINT fk_newsletter_user
    FOREIGN KEY (user_id) REFERENCES users (user_id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
