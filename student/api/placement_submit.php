<?php
require_once __DIR__ . '/../../includes/rbac.php';
require_once __DIR__ . '/../../includes/utils.php';
require_once __DIR__ . '/../../includes/tasks.php';
require_once __DIR__ . '/../../includes/ai_mode.php';

header('Content-Type: application/json; charset=utf-8');
$u = require_role('student');

$raw = file_get_contents('php://input');
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if ($raw && strpos($contentType, 'application/json') === 0) {
  $j = json_decode($raw, true);
  if (!empty($j['finish'])) {
    // placement score hesapla
    $st = db()->prepare("
      SELECT q.skill,
             SUM(CASE WHEN qa.is_correct=1 THEN 1 ELSE 0 END) AS correct_cnt,
             COUNT(*) AS total_cnt
      FROM question_attempts qa
      JOIN questions q ON q.id=qa.question_id
      WHERE qa.user_id=? AND qa.attempt_type='placement'
      GROUP BY q.skill
    ");
    $st->execute([$u['id']]);
    $rows = $st->fetchAll();

    $scores = [];
    foreach ($rows as $r) {
      $pct = ($r['total_cnt'] > 0) ? round(100 * $r['correct_cnt'] / $r['total_cnt']) : 0;
      $scores[$r['skill']] = $pct;
    }

    // eksik skill varsa 0 say
    foreach (['vocab','grammar','reading','listening','writing'] as $sk) {
      if (!isset($scores[$sk])) $scores[$sk]=0;
    }

    $level = level_from_scores($scores);

    $up = db()->prepare("UPDATE users SET placement_completed=1, level=? WHERE id=?");
    $up->execute([$level, $u['id']]);

    // After placement, create initial AI tasks (topic-based). If no attempt data exists yet,
    // the task generator will fall back to a random topic (if topics are defined in questions).
    refresh_tasks_for_user((int)$u['id'], 3);
    // Update the student's preferred learning mode (reading vs audio) after placement.
    update_user_preferred_mode((int)$u['id']);

    echo json_encode(['ok'=>true,'level'=>$level,'scores'=>$scores], JSON_UNESCAPED_UNICODE);
    exit;
  }
}

$qid = (int)($_POST['question_id'] ?? 0);
$ans = trim($_POST['user_answer'] ?? '');

if (!$qid) { echo json_encode(['ok'=>false]); exit; }

$q = db()->prepare("SELECT id, correct_answer, explanation, example_sentence, choices_json FROM questions WHERE id=?");
$q->execute([$qid]);
$row = $q->fetch();
if(!$row){ echo json_encode(['ok'=>false]); exit; }

// correct_answer: MCQ ise index string ("1"), writing ise basit equals (MVP)
$isCorrect = false;
if ($row['choices_json']) {
  $isCorrect = ((string)$ans === (string)$row['correct_answer']);
} else {
  $isCorrect = (mb_strtolower($ans) === mb_strtolower((string)$row['correct_answer']));
}

$ins = db()->prepare("INSERT INTO question_attempts(user_id,question_id,is_correct,user_answer,attempt_type) VALUES(?,?,?,?, 'placement')");
$ins->execute([$u['id'],$qid, $isCorrect?1:0, $ans]);

// küçük puan
$pts = $isCorrect ? 5 : 1;
db()->prepare("UPDATE users SET points = points + ? WHERE id=?")->execute([$pts, $u['id']]);
award_badges_if_needed($u['id']);
update_user_preferred_mode((int)$u['id']);

echo json_encode([
  'ok'=>true,
  'correct'=>$isCorrect,
  'explanation'=>$row['explanation'],
  'example'=>$row['example_sentence'],
  'points_added'=>$pts
], JSON_UNESCAPED_UNICODE);
