-- Add admin-created practice tests (global templates)
-- Run this if you already have an existing DB and do NOT want to re-import install.sql

CREATE TABLE IF NOT EXISTS practice_tests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(180) NOT NULL,
  topic VARCHAR(120) NOT NULL,
  skill ENUM('vocab','grammar','reading','listening','writing') NOT NULL,
  difficulty TINYINT NOT NULL DEFAULT 1,
  question_count INT NOT NULL DEFAULT 10,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX(topic), INDEX(skill), INDEX(difficulty), INDEX(is_active)
);

CREATE TABLE IF NOT EXISTS practice_test_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  test_id INT NOT NULL,
  question_id INT NOT NULL,
  position INT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(test_id) REFERENCES practice_tests(id) ON DELETE CASCADE,
  FOREIGN KEY(question_id) REFERENCES questions(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_test_q (test_id, question_id),
  INDEX(test_id), INDEX(question_id)
);
