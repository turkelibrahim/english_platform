CREATE DATABASE IF NOT EXISTS english_platform
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE english_platform;

-- USERS
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  role ENUM('student','admin') NOT NULL DEFAULT 'student',
  username VARCHAR(60) NOT NULL UNIQUE,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  full_name VARCHAR(120) NOT NULL,
  avatar_url VARCHAR(255) NULL,
  level VARCHAR(10) NULL,                -- A1/A2/B1/B2/C1
  placement_completed TINYINT(1) NOT NULL DEFAULT 0,
  theme ENUM('light','dark') NOT NULL DEFAULT 'light',
  preferred_mode ENUM('reading','audio','balanced') NOT NULL DEFAULT 'balanced',
  preferred_mode_updated_at DATETIME NULL,
  points INT NOT NULL DEFAULT 0,
  streak INT NOT NULL DEFAULT 0,
  last_active_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- QUOTE OF THE DAY
CREATE TABLE quotes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  quote_text VARCHAR(255) NOT NULL,
  author VARCHAR(120) NULL
);


-- APP STATE (for sequential quote rotation)
CREATE TABLE app_state (
  `key` VARCHAR(60) PRIMARY KEY,
  `value` VARCHAR(255) NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
-- QUESTIONS
CREATE TABLE questions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  skill ENUM('vocab','grammar','reading','listening','writing') NOT NULL,
  topic VARCHAR(120) NULL,               -- e.g., Past Simple

  difficulty TINYINT NOT NULL DEFAULT 1,   -- 1..5
  is_placement TINYINT(1) NOT NULL DEFAULT 0,
  prompt TEXT NOT NULL,
  choices_json JSON NULL,                  -- MCQ for vocab/grammar/reading/listening
  correct_answer TEXT NULL,                -- for writing or MCQ key
  media_url VARCHAR(255) NULL,             -- audio/image
  hint TEXT NULL,
  explanation TEXT NULL,
  example_sentence TEXT NULL,
  tags VARCHAR(255) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- LESSONS (content library)
CREATE TABLE lessons (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(180) NOT NULL,
  topic VARCHAR(120) NULL,               -- e.g., Past Simple

  level VARCHAR(10) NULL,
  skill ENUM('vocab','grammar','reading','listening','writing') NOT NULL,
  material_type ENUM('reading','visual','audio') NOT NULL,
  body_html MEDIUMTEXT NOT NULL,
  difficulty TINYINT NOT NULL DEFAULT 1,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ATTEMPTS (history + analytics)
CREATE TABLE question_attempts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  question_id INT NOT NULL,
  task_id INT NULL,
  lesson_id INT NULL,

  is_correct TINYINT(1) NOT NULL,
  user_answer TEXT NULL,
  attempt_type ENUM('placement','practice','level_check') NOT NULL,

  -- For learning-mode estimation (only Task + Lesson quiz attempts are counted)
  context_source ENUM('task','lesson','other') NOT NULL DEFAULT 'other',
  context_mode ENUM('reading','audio') NULL,

  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY(question_id) REFERENCES questions(id) ON DELETE CASCADE,
  INDEX(user_id), INDEX(question_id), INDEX(task_id), INDEX(lesson_id), INDEX(context_source), INDEX(context_mode)
);


-- TASKS (AI-assigned practice sets)
CREATE TABLE user_tasks (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  title VARCHAR(180) NOT NULL,
  topic VARCHAR(120) NOT NULL,
  status ENUM('open','in_progress','done') NOT NULL DEFAULT 'open',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  due_at DATETIME NULL,
  completed_at DATETIME NULL,
  FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX(user_id), INDEX(status), INDEX(topic)
);

CREATE TABLE user_task_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  task_id INT NOT NULL,
  question_id INT NOT NULL,
  position INT NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_task_question(task_id, question_id),
  FOREIGN KEY(task_id) REFERENCES user_tasks(id) ON DELETE CASCADE,
  FOREIGN KEY(question_id) REFERENCES questions(id) ON DELETE CASCADE,
  INDEX(task_id), INDEX(question_id)
);

-- TASK RESULTS (test reports)
CREATE TABLE task_results (
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


-- FAVORITES (lessons or questions)
CREATE TABLE favorites (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  fav_type ENUM('lesson','question') NOT NULL,
  ref_id INT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_fav(user_id, fav_type, ref_id),
  FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX(user_id)
);

-- NOTEBOOK
CREATE TABLE notebook_entries (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  term VARCHAR(120) NOT NULL,
  meaning VARCHAR(255) NULL,
  note TEXT NULL,
  example TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX(user_id)
);

-- REPORTS / ERROR FEEDBACK
CREATE TABLE reports (
  id INT AUTO_INCREMENT PRIMARY KEY,
  reporter_id INT NOT NULL,
  role ENUM('student','admin') NOT NULL,
  category VARCHAR(60) NOT NULL,
  page VARCHAR(120) NULL,
  message TEXT NOT NULL,
  status ENUM('new','reviewing','resolved') NOT NULL DEFAULT 'new',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(reporter_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX(reporter_id), INDEX(status)
);

-- BADGES
CREATE TABLE badges (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(60) NOT NULL UNIQUE,
  title VARCHAR(120) NOT NULL,
  description VARCHAR(255) NOT NULL,
  points_required INT NOT NULL DEFAULT 0
);

CREATE TABLE user_badges (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  badge_id INT NOT NULL,
  earned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_badge(user_id, badge_id),
  FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY(badge_id) REFERENCES badges(id) ON DELETE CASCADE
);


-- ACHIEVEMENT NOTIFICATIONS
CREATE TABLE notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  type ENUM('badge','milestone','system') NOT NULL DEFAULT 'badge',
  title VARCHAR(180) NOT NULL,
  message VARCHAR(255) NOT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX(user_id), INDEX(is_read)
);
-- REMINDER LOG
CREATE TABLE reminder_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reason VARCHAR(120) NOT NULL,
  FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Seed
INSERT INTO quotes(quote_text, author) VALUES
('Small steps every day add up to big results.', 'Unknown'),
('Consistency beats intensity.', 'Unknown'),
('Progress, not perfection.', 'Unknown'),
('Mistakes are proof that you are trying.', 'Unknown'),
('You don’t have to be great to start, but you have to start to be great.', 'Zig Ziglar'),
('Practice makes progress.', 'Unknown'),
('One day or day one. You decide.', 'Unknown'),
('Learning is a journey, not a race.', 'Unknown'),
('The best time to learn is now.', 'Unknown'),
('Don’t compare; improve.', 'Unknown'),
('Today’s effort is tomorrow’s confidence.', 'Unknown'),
('Little by little, a little becomes a lot.', 'Tanzanian Proverb'),
('Your future self will thank you.', 'Unknown'),
('Focus on what you can control: your effort.', 'Unknown'),
('Every sentence you learn is a new door.', 'Unknown'),
('A goal without a plan is just a wish.', 'Antoine de Saint-Exupéry'),
('If it doesn’t challenge you, it won’t change you.', 'Unknown'),
('Success is the sum of small efforts repeated day in and day out.', 'Robert Collier'),
('The expert was once a beginner.', 'Helen Hayes'),
('Discipline is choosing between what you want now and what you want most.', 'Unknown'),
('Believe you can, and you’re halfway there.', 'Theodore Roosevelt'),
('The only limit is the one you set yourself.', 'Unknown'),
('Learning never exhausts the mind.', 'Leonardo da Vinci'),
('Don’t be afraid to be a beginner.', 'Unknown'),
('Your pace is still progress.', 'Unknown');
INSERT INTO app_state(`key`,`value`) VALUES ('quote_pointer','0');
INSERT INTO badges(code,title,description,points_required) VALUES
('starter','Starter','Earn your first 50 points.',50),
('steady','Steady','Reach 200 points.',200),
('climber','Climber','Reach 500 points.',500);

-- Example placement questions
INSERT INTO questions(skill,difficulty,is_placement,prompt,choices_json,correct_answer,hint,explanation,example_sentence,tags)
VALUES
('grammar',1,1,'Choose the correct sentence.',
 JSON_ARRAY('She go to school every day.','She goes to school every day.','She going to school every day.','She gone to school every day.'),
 '1','Look at subject-verb agreement.','Third person singular takes -s in present simple.','She goes to school every day.','present simple'),
('vocab',1,1,'What is the closest meaning of "tiny"?',
 JSON_ARRAY('Huge','Small','Angry','Fast'),
 '1','Think about size.','"Tiny" means very small.','A tiny bird sat on the window.','adjectives'),
('reading',1,1,'Read: "Tom is late because of traffic." Why is Tom late?',
 JSON_ARRAY('Because he slept.','Because of traffic.','Because he is sick.','Because he forgot.'),
 '1',NULL,'The sentence says "because of traffic".',NULL,'reading');


-- Password reset tokens
CREATE TABLE IF NOT EXISTS password_resets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX(token_hash),
  INDEX(user_id),
  INDEX(expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ADMIN-CREATED PRACTICE TESTS (global templates)
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
