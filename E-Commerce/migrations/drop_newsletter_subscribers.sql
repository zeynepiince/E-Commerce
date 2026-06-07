-- Remove legacy newsletter subscriber list.
-- Run: mysql -u root -proot -P 8889 chatbotv2_db < migrations/drop_newsletter_subscribers.sql

DROP TABLE IF EXISTS newsletter_subscribers;
