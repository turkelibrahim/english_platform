<?php
require_once __DIR__ . '/../../includes/rbac.php';
require_once __DIR__ . '/../../includes/utils.php';

header('Content-Type: application/json; charset=utf-8');
$u = require_role('student');

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

$qid = (int)($_POST['question_id'] ?? 0);
$ans = trim((string)($_POST['user_answer'] ?? ''));

$quiz = $_SESSION['level_quiz'] ?? null;
if (!is_array($quiz) || empty($quiz['qids']) || !is_array($quiz['qids'])) {
  echo json_encode(['ok' => false, 'error' => 'Quiz not started'], JSON_UNESCAPED_UNICODE);
  exit;
}

$qids = array_map('intval', $quiz['qids']);
if ($qid <= 0 || !in_array($qid, $qids, true)) {
  echo json_encode(['ok' => false, 'error' => 'Invalid question'], JSON_UNESCAPED_UNICODE);
  exit;
}

// Load question
$st = db()->prepare("SELECT id, skill, difficulty, prompt, correct_answer, explanation, example_sentence, choices_json, media_url FROM questions WHERE id=? AND is_active=1 LIMIT 1");
$st->execute([$qid]);
$q = $st->fetch();
if (!$q) {
  echo json_encode(['ok' => false, 'error' => 'Question not found'], JSON_UNESCAPED_UNICODE);
  exit;
}

$choices = $q['choices_json'] ? json_decode($q['choices_json'], true) : null;
$isCorrect = false;
if ($choices) {
  $isCorrect = ((string)$ans === (string)$q['correct_answer']);
} else {
  $isCorrect = (mb_strtolower($ans) === mb_strtolower((string)$q['correct_answer']));
}

// Context mode for listening questions
$media = (string)($q['media_url'] ?? '');
$contextMode = preg_match('/\.mp3(\?|$)/i', $media) ? 'audio' : 'reading';

// Persist attempt
try {
  db()->prepare(
    "INSERT INTO question_attempts(user_id,question_id,task_id,lesson_id,is_correct,user_answer,attempt_type,context_source,context_mode)
     VALUES(?,?,?,?,?,?, 'level_check', 'level_check', ?)"
  )->execute([$u['id'], $qid, null, null, $isCorrect ? 1 : 0, $ans, $contextMode]);
} catch (Throwable $e) {
  // If db enum not migrated yet, fallback to practice so the UI still works.
  db()->prepare(
    "INSERT INTO question_attempts(user_id,question_id,task_id,lesson_id,is_correct,user_answer,attempt_type,context_source,context_mode)
     VALUES(?,?,?,?,?,?, 'practice', 'level_check', ?)"
  )->execute([$u['id'], $qid, null, null, $isCorrect ? 1 : 0, $ans, $contextMode]);
}

// Store in session for scoring
if (!isset($quiz['answers']) || !is_array($quiz['answers'])) {
  $quiz['answers'] = [];
}
$quiz['answers'][$qid] = [
  'skill' => (string)$q['skill'],
  'correct' => $isCorrect ? 1 : 0
];
$_SESSION['level_quiz'] = $quiz;

// Remaining
$idx = (int)($quiz['idx'] ?? 0);
$nextIdx = $idx + 1;
$remaining = max(0, count($qids) - $nextIdx);

echo json_encode([
  'ok' => true,
  'correct' => $isCorrect,
  'explanation' => $q['explanation'],
  'example' => $q['example_sentence'],
  'quiz_remaining' => $remaining,
  'can_finish' => ($remaining === 0),
  'next_url' => BASE_URL . '/student/level_quiz.php?next=1',
  'finish_url' => BASE_URL . '/student/level_quiz.php?finish=1'
], JSON_UNESCAPED_UNICODE);
