<?php
require_once __DIR__ . '/../../includes/rbac.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../ai/gemini_chat.php';

header('Content-Type: application/json; charset=utf-8');
$u = require_role('admin');
csrf_check();

$skill = $_POST['skill'] ?? 'vocab';
$difficulty = (int)($_POST['difficulty'] ?? 1);
$level = strtoupper(trim((string)($_POST['level'] ?? '')));
$levelMap = ['A1'=>1,'A2'=>2,'B1'=>3,'B2'=>4,'C1'=>5];
if (!isset($levelMap[$level])) { $level = ''; }
if ($level !== '') { $difficulty = (int)$levelMap[$level]; }
$topic = trim($_POST['topic'] ?? '');
$qcount = (int)($_POST['qcount'] ?? 10);

if (!in_array($skill, ['vocab','grammar','reading','listening','writing'], true)) {
  echo json_encode(['ok'=>false,'msg'=>'invalid_skill'], JSON_UNESCAPED_UNICODE);
  exit;
}
if ($difficulty < 1) $difficulty = 1;
if ($difficulty > 5) $difficulty = 5;
if (!in_array($qcount, [5,10,20], true)) $qcount = 10;

if ($topic === '') {
  echo json_encode(['ok'=>false,'msg'=>'missing_topic'], JSON_UNESCAPED_UNICODE);
  exit;
}

if (!gemini_key_is_set()) {
  echo json_encode(['ok'=>false,'msg'=>'missing_gemini_key'], JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  $wantsChoices = ($skill !== 'writing');

  $prompt = "You are an English learning content designer.\n"
    . "Create EXACTLY {$qcount} high-quality questions for an English learning platform.\n"
    . "Return ONLY valid JSON in this exact shape:\n"
    . "{\"questions\":[{\"prompt\":\"...\",\"choices\":null|[\"...\",\"...\",\"...\",\"...\"],\"correct_answer\":0|1|2|3|\"text\",\"hint\":\"...\",\"explanation\":\"...\",\"example_sentence\":\"...\"}, ...]}\n"
    . "Rules:\n"
    . "- Use the given Topic exactly; DO NOT invent a different topic.\n"
    . "- prompt: clear and short.\n"
    . "- hint: 1 sentence.\n"
    . "- explanation: 1-3 short sentences.\n"
    . "- example_sentence: 1 natural sentence.\n"
    . "- Do not include markdown.\n\n"
    . "Skill: {$skill}\n"
    . "Difficulty (1-5): {$difficulty}\n"
    . ("CEFR Level: " . ($level !== '' ? $level : '')) . "\n"
    . "Topic: {$topic}\n";

  if ($wantsChoices) {
    $prompt .= "Make ALL questions multiple-choice with EXACTLY 4 options. correct_answer must be an integer index 0-3.\n";
  } else {
    $prompt .= "Make ALL questions free-text with choices=null. correct_answer must be the exact expected text.\n";
  }

  $out = askGeminiJson($prompt, ['temperature'=>0.3, 'max_tokens'=>4096]);

  if (!is_array($out) || !isset($out['questions']) || !is_array($out['questions'])) {
    throw new Exception('bad_shape');
  }

  $qs = $out['questions'];
  $qs = array_values($qs);

  if (count($qs) !== $qcount) {
    throw new Exception('bad_count');
  }

  $norm = [];
  foreach ($qs as $it) {
    if (!is_array($it)) throw new Exception('bad_item');

    $qPrompt = trim((string)($it['prompt'] ?? ''));
    if ($qPrompt === '') throw new Exception('bad_prompt');

    $choices = $it['choices'] ?? null;
    $choicesArr = null;
    if (is_array($choices)) {
      $choicesArr = array_values(array_map('strval', $choices));
      $choicesArr = array_slice($choicesArr, 0, 4);
      if (count($choicesArr) !== 4) throw new Exception('bad_choices');
    }

    $correct = $it['correct_answer'] ?? null;
    if ($choicesArr !== null) {
      $ci = is_numeric($correct) ? (int)$correct : -1;
      if ($ci < 0 || $ci > 3) throw new Exception('bad_correct');
      $correctNorm = (string)$ci;
    } else {
      $correctNorm = trim((string)$correct);
      if ($correctNorm === '') throw new Exception('bad_correct');
    }

    $hint = trim((string)($it['hint'] ?? ''));
    $exp  = trim((string)($it['explanation'] ?? ''));
    $exs  = trim((string)($it['example_sentence'] ?? ''));

    $norm[] = [
      'prompt' => $qPrompt,
      'choices' => $choicesArr,
      'correct_answer' => $correctNorm,
      'hint' => $hint,
      'explanation' => $exp,
      'example_sentence' => $exs,
    ];
  }

  echo json_encode(['ok'=>true, 'questions'=>$norm], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  echo json_encode(['ok'=>false, 'msg'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
