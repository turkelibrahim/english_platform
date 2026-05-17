<?php
require_once __DIR__ . '/../../includes/rbac.php';
require_once __DIR__ . '/../../includes/utils.php';
require_once __DIR__ . '/../../includes/ai_mode.php';
require_once __DIR__ . '/../../ai/gemini_chat.php';

header('Content-Type: application/json; charset=utf-8');
$u = require_role('student');

$qid = (int)($_POST['question_id'] ?? 0);
$ans = trim($_POST['user_answer'] ?? '');
$taskId = (int)($_POST['task_id'] ?? 0);
if ($taskId <= 0) $taskId = null;
$lessonId = (int)($_POST['lesson_id'] ?? 0);
if ($lessonId <= 0) $lessonId = null;

if (!$qid) { echo json_encode(['ok'=>false], JSON_UNESCAPED_UNICODE); exit; }

$q = db()->prepare("SELECT id, skill, difficulty, prompt, correct_answer, explanation, example_sentence, choices_json, media_url FROM questions WHERE id=? AND is_active=1");
$q->execute([$qid]);
$row = $q->fetch();
if(!$row){ echo json_encode(['ok'=>false], JSON_UNESCAPED_UNICODE); exit; }

$isCorrect = false;
if ($row['choices_json']) {
  $isCorrect = ((string)$ans === (string)$row['correct_answer']);
} else {
  $isCorrect = (mb_strtolower($ans) === mb_strtolower((string)$row['correct_answer']));
}


$contextSource = ($taskId !== null) ? 'task' : (($lessonId !== null) ? 'lesson' : 'other');
$media = (string)($row['media_url'] ?? '');
$contextMode = preg_match('/\.mp3(\?|$)/i', $media) ? 'audio' : 'reading';
db()->prepare("INSERT INTO question_attempts(user_id,question_id,task_id,lesson_id,is_correct,user_answer,attempt_type,context_source,context_mode)
               VALUES(?,?,?,?,?,?, 'practice',?,?)")
  ->execute([$u['id'],$qid, $taskId, $lessonId, $isCorrect?1:0, $ans, $contextSource, $contextMode]);

$pts = $isCorrect ? 6 : 2;
db()->prepare("UPDATE users SET points = points + ? WHERE id=?")->execute([$pts, $u['id']]);
$newBadges = award_badges_if_needed((int)$u['id']);
update_user_preferred_mode((int)$u['id']);


// If missing explanation/example, try to generate with Gemini (only if API key is set).
if ((empty($row['explanation']) || empty($row['example_sentence'])) && function_exists('gemini_key_is_set') && gemini_key_is_set()) {
  try {
    $choicesArr = [];
    if (!empty($row['choices_json'])) {
      $decoded = json_decode($row['choices_json'], true);
      if (is_array($decoded)) $choicesArr = $decoded;
    }

    $correctText = (string)$row['correct_answer'];
    if (!empty($choicesArr)) {
      $ci = (int)$row['correct_answer'];
      if (isset($choicesArr[$ci])) $correctText = $choicesArr[$ci];
    }

    $aiPrompt = "You are an English learning assistant.\n"
      . "Explain why the student's answer is correct or incorrect.\n"
      . "Return ONLY valid JSON with keys: explanation, example_sentence.\n"
      . "Rules:\n"
      . "- Explanation: 1-3 short sentences.\n"
      . "- Example sentence: natural and relevant to the concept.\n"
      . "- Do not include markdown.\n\n"
      . "Skill: ".$row['skill']."\n"
      . "Difficulty (1-5): ".((int)$row['difficulty'])."\n"
      . "Question prompt: ".$row['prompt']."\n";

    if (!empty($choicesArr)) {
      $aiPrompt .= "Choices:\n";
      foreach ($choicesArr as $i=>$c) {
      $aiPrompt .= $i . ") " . $c . "
";
    }
      $aiPrompt .= "Correct choice index: ".(string)$row['correct_answer']."\n";
      $aiPrompt .= "Correct choice text: ".$correctText."\n";
    } else {
      $aiPrompt .= "Correct answer: ".$correctText."\n";
    }

    $aiPrompt .= "Student answer: ".$ans."\n";
    $aiPrompt .= "Result: ".($isCorrect ? "correct" : "incorrect")."\n";

    $out = askGeminiJson($aiPrompt, ['temperature'=>0.2, 'max_tokens'=>256]);
    $aiExp = trim((string)($out['explanation'] ?? ''));
    $aiEx  = trim((string)($out['example_sentence'] ?? ''));

    // Save only if missing
    if (empty($row['explanation']) && $aiExp !== '') {
      db()->prepare("UPDATE questions SET explanation=? WHERE id=?")->execute([$aiExp, $qid]);
      $row['explanation'] = $aiExp;
    }
    if (empty($row['example_sentence']) && $aiEx !== '') {
      db()->prepare("UPDATE questions SET example_sentence=? WHERE id=?")->execute([$aiEx, $qid]);
      $row['example_sentence'] = $aiEx;
    }
  } catch (Exception $e) {
    // If Gemini fails, silently continue with existing data.
  }
}

$resp = [
  'ok'=>true,
  'correct'=>$isCorrect,
  'explanation'=>$row['explanation'],
  'example'=>$row['example_sentence'],
  'points_added'=>$pts,
  'new_badges'=>$newBadges
];

// If this was a Task question, tell the UI whether the student can finish the test.
if ($taskId > 0) {
  try {
    $rem = db()->prepare(
      "SELECT COUNT(*)
       FROM user_task_items uti
       LEFT JOIN question_attempts qa
         ON qa.user_id=? AND qa.task_id=? AND qa.question_id=uti.question_id
       WHERE uti.task_id=? AND qa.id IS NULL"
    );
    // Use the authenticated user id
    $rem->execute([$u['id'], $taskId, $taskId]);
    $remaining = (int)$rem->fetchColumn();

    $resp['task_remaining'] = $remaining;
    $resp['task_can_finish'] = ($remaining === 0);
    $resp['next_url'] = BASE_URL . '/student/task_next.php?task_id=' . $taskId;
    $resp['finish_url'] = BASE_URL . '/student/task_result.php?task_id=' . $taskId;
  } catch (Throwable $e) {
    // ignore
  }
}

echo json_encode($resp, JSON_UNESCAPED_UNICODE);
