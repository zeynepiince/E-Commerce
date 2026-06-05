-- Chatbot thumbs-up/down feedback with confidence and experiment metadata.
-- Run: mysql -u root -proot -P 8888 chatbotv2_db < migrations/add_chatbot_feedback.sql

CREATE TABLE IF NOT EXISTS chatbot_feedback (
  feedback_id INT NOT NULL AUTO_INCREMENT,
  user_id INT DEFAULT NULL,
  helpful TINYINT(1) NOT NULL,
  intent VARCHAR(50) DEFAULT NULL,
  source VARCHAR(50) DEFAULT NULL,
  confidence DECIMAL(4,2) DEFAULT NULL,
  used_ai TINYINT(1) NOT NULL DEFAULT 0,
  escalated_to_human TINYINT(1) NOT NULL DEFAULT 0,
  experiment_mode VARCHAR(20) DEFAULT NULL,
  experiment_bucket VARCHAR(20) DEFAULT NULL,
  experiment_variants JSON DEFAULT NULL,
  user_message TEXT,
  bot_reply TEXT,
  page VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (feedback_id),
  KEY idx_chatbot_feedback_intent (intent),
  KEY idx_chatbot_feedback_created (created_at),
  CONSTRAINT fk_chatbot_feedback_user FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
