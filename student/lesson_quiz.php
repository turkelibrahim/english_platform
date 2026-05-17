<?php
require_once __DIR__ . '/../includes/rbac.php';

$u = require_role('student');
$lessonId = (int)($_GET['id'] ?? 0);
if ($lessonId <= 0) { header('Location: '.BASE_URL.'/student/lessons.php'); exit; }

$st = db()->prepare("SELECT id, topic, skill FROM lessons WHERE id=? AND is_active=1 LIMIT 1");
$st->execute([$lessonId]);
$lesson = $st->fetch();
if (!$lesson) { header('Location: '.BASE_URL.'/student/lessons.php'); exit; }

$topic = $lesson['topic'] ?? null;
$skill = $lesson['skill'] ?? null;

// Prefer questions from the same topic. Fallback to same skill. Placement questions excluded.
$qid = null;
if ($topic) {
  $qs = db()->prepare("SELECT id FROM questions WHERE is_active=1 AND is_placement=0 AND topic=? ORDER BY RAND() LIMIT 1");
  $qs->execute([$topic]);
  $qid = (int)($qs->fetchColumn() ?: 0);
}

if (!$qid && $skill) {
  $qs = db()->prepare("SELECT id FROM questions WHERE is_active=1 AND is_placement=0 AND skill=? ORDER BY RAND() LIMIT 1");
  $qs->execute([$skill]);
  $qid = (int)($qs->fetchColumn() ?: 0);
}

if (!$qid) {
  header('Location: '.BASE_URL.'/student/lesson_view.php?id='.$lessonId.'&no_quiz=1');
  exit;
}

header('Location: '.BASE_URL.'/student/practice_view.php?qid='.$qid.'&lesson_id='.$lessonId);
exit;
