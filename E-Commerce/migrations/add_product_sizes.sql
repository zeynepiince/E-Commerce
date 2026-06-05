-- Ürün bedenleri (virgül veya / ile ayrılmış: "S,M,L" veya "39,40,41")
-- Run: mysql -u root -proot -P 8889 chatbotv2_db < migrations/add_product_sizes.sql

ALTER TABLE products
  ADD COLUMN sizes VARCHAR(255) NULL DEFAULT NULL AFTER stock_quantity;
