<?php
require_once __DIR__ . '/../includes/rbac.php';

$u = require_role('student');
$taskId = (int)($_GET['task_id'] ?? 0);
if ($taskId <= 0) { header('Location: '.BASE_URL.'/student/dashboard.php'); exit; }

// Ensure task belongs to student
$st = db()->prepare("SELECT id, user_id, title, topic, status FROM user_tasks WHERE id=? AND user_id=? LIMIT 1");
$st->execute([$taskId, $u['id']]);
$task = $st->fetch();
if (!$task) { header('Location: '.BASE_URL.'/student/dashboard.php'); exit; }

// Total questions
$totSt = db()->prepare("SELECT COUNT(*) FROM user_task_items WHERE task_id=?");
$totSt->execute([$taskId]);
$total = (int)$totSt->fetchColumn();

if ($total <= 0) {
  header('Location: '.BASE_URL.'/student/dashboard.php');
  exit;
}

// Answered distinct questions
$ansSt = db()->prepare("SELECT COUNT(DISTINCT question_id) FROM question_attempts WHERE user_id=? AND task_id=?");
$ansSt->execute([$u['id'], $taskId]);
$answered = (int)$ansSt->fetchColumn();

if ($answered < $total) {
  // Not finished yet
  header('Location: '.BASE_URL.'/student/task_next.php?task_id='.$taskId);
  exit;
}

// Correct count (latest attempt per question)
$correct = 0;
try {
  $cst = db()->prepare(
    "SELECT SUM(q2.is_correct) AS correct_count
     FROM (
        SELECT qa.question_id, MAX(qa.id) AS max_id
        FROM question_attempts qa
        WHERE qa.user_id=? AND qa.task_id=?
        GROUP BY qa.question_id
     ) t
     JOIN question_attempts q2 ON q2.id=t.max_id"
  );
  $cst->execute([$u['id'], $taskId]);
  $correct = (int)($cst->fetchColumn() ?? 0);
} catch (Throwable $e) {
  // fallback
  $cst = db()->prepare("SELECT SUM(is_correct) FROM question_attempts WHERE user_id=? AND task_id=?");
  $cst->execute([$u['id'], $taskId]);
  $correct = (int)($cst->fetchColumn() ?? 0);
}

$wrong = max(0, $total - $correct);
$scorePct = $total ? round(($correct / $total) * 100, 1) : 0.0;

// Ensure task_results table exists (safe for local use)
try {
  db()->exec(
    "CREATE TABLE IF NOT EXISTS task_results (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
  );
} catch (Throwable $e) {
  // ignore
}

// Upsert the result
try {
  $ins = db()->prepare(
    "INSERT INTO task_results (user_id, task_id, total_questions, correct_count, wrong_count, score_pct)
     VALUES (?,?,?,?,?,?)
     ON DUPLICATE KEY UPDATE total_questions=VALUES(total_questions), correct_count=VALUES(correct_count), wrong_count=VALUES(wrong_count), score_pct=VALUES(score_pct)"
  );
  $ins->execute([$u['id'], $taskId, $total, $correct, $wrong, $scorePct]);
} catch (Throwable $e) {
  // ignore
}

// Mark task done (safe if completed_at column doesn't exist)
try {
  db()->prepare("UPDATE user_tasks SET status='done', completed_at=NOW() WHERE id=? AND user_id=?")
    ->execute([$taskId, $u['id']]);
} catch (Throwable $e) {
  try {
    db()->prepare("UPDATE user_tasks SET status='done' WHERE id=? AND user_id=?")
      ->execute([$taskId, $u['id']]);
  } catch (Throwable $e2) {}
}

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Task Result</title>
  <link rel="stylesheet" href="<?=BASE_URL?>/public/assets/css/app.css">
</head>
<body data-theme="<?=e($u['theme'] ?? 'light')?>">
<div class="container">
  <?php $navPage="Practice"; $navActive="practice"; include __DIR__ . '/../includes/partials/student_nav.php'; ?>

  <div class="card" style="margin-top:18px">
    <div class="h1">Test Finished ✅</div>
    <div class="muted" style="margin-top:6px">
      <?=e($task['title'] ?? 'Task')?>
      <?php if(!empty($task['topic'])): ?> · Topic: <b><?=e($task['topic'])?></b><?php endif; ?>
    </div>
    <div class="hr"></div>

    <div class="grid" style="grid-template-columns:1fr 1fr 1fr; gap:12px">
      <div class="card" style="box-shadow:none">
        <div class="muted">Correct</div>
        <div style="font-size:28px; font-weight:900; margin-top:6px"><?= (int)$correct ?></div>
      </div>
      <div class="card" style="box-shadow:none">
        <div class="muted">Wrong</div>
        <div style="font-size:28px; font-weight:900; margin-top:6px"><?= (int)$wrong ?></div>
      </div>
      <div class="card" style="box-shadow:none">
        <div class="muted">Score</div>
        <div style="font-size:28px; font-weight:900; margin-top:6px"><?= e($scorePct) ?>%</div>
      </div>
    </div>

    <div class="hr"></div>
    <div class="row" style="justify-content:space-between">
      <a class="btn" href="<?=BASE_URL?>/student/profile.php">View in Profile</a>
      <a class="btn primary" href="<?=BASE_URL?>/student/dashboard.php">Back to Dashboard</a>
    </div>
  </div>
</div>
</body>
</html>
