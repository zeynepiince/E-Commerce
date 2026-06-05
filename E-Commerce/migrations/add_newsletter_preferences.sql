CREATE TABLE IF NOT EXISTS newsletter_subscribers (
  subscriber_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  email VARCHAR(255) NOT NULL,
  user_id INT NULL DEFAULT NULL,
  source VARCHAR(50) NOT NULL DEFAULT 'homepage',
  subscribed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (subscriber_id),
  UNIQUE KEY uq_newsletter_email (email),
  KEY idx_newsletter_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE users
  ADD COLUMN newsletter_opt_in TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN email_notifications TINYINT(1) NOT NULL DEFAULT 1;
