-- Social login provider IDs (run once; oauth_callback also auto-adds columns if missing)
ALTER TABLE users
  ADD COLUMN google_id VARCHAR(255) NULL DEFAULT NULL AFTER password_hash,
  ADD COLUMN facebook_id VARCHAR(255) NULL DEFAULT NULL AFTER google_id;
