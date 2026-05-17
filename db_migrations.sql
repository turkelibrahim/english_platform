-- If you already have an existing database, run this once.
-- Adds the new attempt type used for Level Check Quiz.

ALTER TABLE question_attempts
  MODIFY attempt_type ENUM('placement','practice','level_check') NOT NULL;
