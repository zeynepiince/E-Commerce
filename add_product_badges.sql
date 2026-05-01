-- Ürün etiketleri (badges) - Hepsiburada/Trendyol tarzı
-- Çalıştırmak: mysql -u root -proot -P 8888 chatbotv2_db < migrations/add_product_badges.sql
-- Not: badges sütunu zaten varsa hata verebilir, yok sayın.

ALTER TABLE products ADD COLUMN badges JSON DEFAULT NULL COMMENT 'Ürün etiketleri: ["hizli-teslimat","en-cok-satan"]';
