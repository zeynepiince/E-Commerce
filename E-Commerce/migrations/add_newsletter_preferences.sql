-- Legacy migration: email notification preference on users.
-- newsletter_opt_in was removed; see drop_newsletter_opt_in.sql

ALTER TABLE users
  ADD COLUMN email_notifications TINYINT(1) NOT NULL DEFAULT 1;
