-- Fix sender CHECK constraint: enum allows 'user'/'bot' but chk_1 only allowed 'user'/'system'.
-- Run: mysql -u root -proot -P 8888 chatbotv2_db < migrations/fix_support_interactions_sender_check.sql

ALTER TABLE support_interactions
  DROP CHECK support_interactions_chk_1;

ALTER TABLE support_interactions
  ADD CONSTRAINT support_interactions_chk_1 CHECK (sender IN ('user', 'bot'));
