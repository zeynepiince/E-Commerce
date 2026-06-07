-- Remove legacy newsletter opt-in column (feature removed from UI and codebase).
-- Run: mysql -u root -proot -P 8889 chatbotv2_db < migrations/drop_newsletter_opt_in.sql

ALTER TABLE users DROP COLUMN newsletter_opt_in;
