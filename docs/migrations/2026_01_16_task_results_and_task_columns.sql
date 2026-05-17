-- Adds missing columns/tables for Task completion + Test Reports
-- If you see errors like "Duplicate column name", that item is already applied.

-- 1) user_tasks.completed_at
ALTER TABLE user_tasks
  ADD COLUMN completed_at DATETIME NULL AFTER due_at;

-- 2) user_task_items.created_at
ALTER TABLE user_task_items
  ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;

-- 3) task_results (test reports)
CREATE TABLE IF NOT EXISTS task_results (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  task_id INT NOT NULL,
  total_questions INT NOT NULL,
  correct_count INT NOT NULL,
  wrong_count INT NOT NULL,
  score_pct DECIMAL(5,1) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_task (task_id),
  INDEX idx_user (user_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (task_id) REFERENCES user_tasks(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
