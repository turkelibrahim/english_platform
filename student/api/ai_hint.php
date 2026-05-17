<?php
require_once __DIR__ . '/../../includes/rbac.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../ai/gemini_chat.php';

header('Content-Type: application/json; charset=utf-8');
$u = require_role('student');

// CSRF required (like other AJAX endpoints)
csrf_check();

$qid = (int)($_POST['question_id'] ?? 0);
if ($qid <= 0) {
  echo json_encode(['ok'=>false,'msg'=>'missing_question_id'], JSON_UNESCAPED_UNICODE);
  exit;
}

$st = db()->prepare("SELECT id, skill, difficulty, prompt, choices_json, correct_answer, hint, example_sentence FROM questions WHERE id=? AND is_active=1");
$st->execute([$qid]);
$q = $st->fetch();

if (!$q) {
  echo json_encode(['ok'=>false,'msg'=>'question_not_found'], JSON_UNESCAPED_UNICODE);
  exit;
}


if (!function_exists('gemini_key_is_set') || !gemini_key_is_set()) {
  echo json_encode(['ok'=>false,'msg'=>'missing_gemini_key'], JSON_UNESCAPED_UNICODE);
  exit;
}

// If already has both, just return
if (!empty($q['hint']) && !empty($q['example_sentence'])) {
  echo json_encode(['ok'=>true,'hint'=>$q['hint'],'example'=>$q['example_sentence']], JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  $choices = [];
  if (!empty($q['choices_json'])) {
    $decoded = json_decode($q['choices_json'], true);
    if (is_array($decoded)) $choices = $decoded;
  }

  $prompt = "You are an English learning assistant.\n"
  . "Generate a short hint and one example sentence for this question.\n"
  . "Return ONLY valid JSON with keys: hint, example_sentence.\n"
  . "Rules:\n"
  . "- Keep the hint short (1-2 sentences).\n"
  . "- Example sentence should be natural and at the student's level.\n"
  . "- Do not include markdown.\n\n"
  . "Question skill: ".$q['skill']."\n"
  . "Difficulty (1-5): ".((int)$q['difficulty'])."\n"
  . "Prompt: ".$q['prompt']."\n";

  if (!empty($choices)) {
    $prompt .= "Choices:\n";
    foreach ($choices as $i=>$c) {
      $prompt .= $i . ") " . $c . "\n";
    }
    $prompt .= "Correct choice index: " . (string)$q['correct_answer'] . "\n";
  } else if (!empty($q['correct_answer'])) {
    $prompt .= "Correct answer: " . (string)$q['correct_answer'] . "\n";
  }

  $out = askGeminiJson($prompt, ['temperature'=>0.2, 'max_tokens'=>256]);
  $hint = trim((string)($out['hint'] ?? ''));
  $ex   = trim((string)($out['example_sentence'] ?? ''));

  if ($hint === '' && $ex === '') {
    throw new Exception('EMPTY_AI_OUTPUT');
  }

  // Save only missing fields (do not overwrite teacher-written content)
  if (empty($q['hint']) && $hint !== '') {
    db()->prepare("UPDATE questions SET hint=? WHERE id=?")->execute([$hint, $qid]);
    $q['hint'] = $hint;
  }
  if (empty($q['example_sentence']) && $ex !== '') {
    db()->prepare("UPDATE questions SET example_sentence=? WHERE id=?")->execute([$ex, $qid]);
    $q['example_sentence'] = $ex;
  }

  echo json_encode(['ok'=>true,'hint'=>$q['hint'],'example'=>$q['example_sentence']], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
  echo json_encode(['ok'=>false,'msg'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
