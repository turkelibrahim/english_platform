<?php
require_once __DIR__ . '/../../includes/rbac.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../ai/gemini_chat.php';

header('Content-Type: application/json; charset=utf-8');
$u = require_role('admin');

// CSRF required
csrf_check();

$skill = $_POST['skill'] ?? 'vocab';
$difficulty = (int)($_POST['difficulty'] ?? 1);
$level = strtoupper(trim((string)($_POST['level'] ?? '')));
$levelMap = ['A1'=>1,'A2'=>2,'B1'=>3,'B2'=>4,'C1'=>5];
if (!isset($levelMap[$level])) { $level = ''; }
if ($level !== '') { $difficulty = (int)$levelMap[$level]; }
$topic = trim($_POST['topic'] ?? '');

if (!in_array($skill, ['vocab','grammar','reading','listening','writing'], true)) {
  echo json_encode(['ok'=>false,'msg'=>'invalid_skill'], JSON_UNESCAPED_UNICODE);
  exit;
}
if ($difficulty < 1) $difficulty = 1;
if ($difficulty > 5) $difficulty = 5;

if (!gemini_key_is_set()) {
  echo json_encode(['ok'=>false,'msg'=>'missing_gemini_key'], JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  $wantsChoices = ($skill !== 'writing'); // writing can be free-text
  $prompt = "You are an English learning content designer.\n"
    . "Create ONE high-quality question for an English learning platform.\n"
    . "Return ONLY valid JSON with keys:\n"
    . "prompt, choices, correct_answer, hint, explanation, example_sentence.\n"
    . "Rules:\n"
    . "- prompt: clear and short.\n"
    . "- If choices is not null, it must be an array of EXACTLY 4 strings.\n"
    . "- correct_answer: if choices exists => index 0-3 as a number; else => correct text.\n"
    . "- hint: 1 sentence.\n"
    . "- explanation: 1-3 short sentences.\n"
    . "- example_sentence: one natural example sentence.\n"
    . "- Do not include markdown.\n\n"
    . "Skill: ".$skill."\n"
    . "Difficulty (1-5): ".$difficulty."\n"
    . ("CEFR Level: " . ($level !== '' ? $level : '')) . "\n"
    . ($topic !== '' ? ("Topic: ".$topic."\n") : "");

  if ($wantsChoices) {
    $prompt .= "Make it multiple-choice.\n";
  } else {
    $prompt .= "Make it free-text (no multiple-choice).\n";
  }

  $out = askGeminiJson($prompt, ['temperature'=>0.3, 'max_tokens'=>512]);

  $qPrompt = trim((string)($out['prompt'] ?? ''));
  $choices = $out['choices'] ?? null;
  $correct = $out['correct_answer'] ?? null;
  $hint = trim((string)($out['hint'] ?? ''));
  $exp  = trim((string)($out['explanation'] ?? ''));
  $exs  = trim((string)($out['example_sentence'] ?? ''));

  if ($qPrompt === '') throw new Exception('bad_prompt');

  // Normalize choices
  $choicesArr = null;
  if (is_array($choices)) {
    $choicesArr = array_values(array_map('strval', $choices));
    $choicesArr = array_slice($choicesArr, 0, 4);
    if (count($choicesArr) !== 4) throw new Exception('bad_choices');
  }

  // Normalize correct_answer
  if ($choicesArr !== null) {
    $ci = is_numeric($correct) ? (int)$correct : -1;
    if ($ci < 0 || $ci > 3) throw new Exception('bad_correct');
    $correctNorm = (string)$ci;
  } else {
    $correctNorm = trim((string)$correct);
    if ($correctNorm === '') throw new Exception('bad_correct');
  }

  echo json_encode([
    'ok'=>true,
    'prompt'=>$qPrompt,
    'choices'=>$choicesArr,
    'correct_answer'=>$correctNorm,
    'hint'=>$hint,
    'explanation'=>$exp,
    'example_sentence'=>$exs
  ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
  echo json_encode(['ok'=>false,'msg'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
