<?php
require_once __DIR__ . '/../../includes/rbac.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../ai/gemini_chat.php';

header('Content-Type: application/json; charset=utf-8');

try {
  require_role('admin');
  csrf_check();

  $topic = trim($_POST['topic'] ?? '');
  $skill = trim($_POST['skill'] ?? '');
  $level = trim($_POST['level'] ?? '');
  $materialType = trim($_POST['material_type'] ?? 'text');
  $difficulty = trim($_POST['difficulty'] ?? 'Medium');

  if ($topic === '' || $skill === '' || $level === '') {
    echo json_encode(['ok'=>false,'error'=>'Missing fields']);
    exit;
  }

  // Build a lesson prompt. Return STRICT JSON.
  $prompt = "You are generating a concise language-learning lesson for students.\n".
    "Topic: {$topic}\n".
    "Skill: {$skill}\n".
    "Level: {$level}\n".
    "Difficulty: {$difficulty}\n\n".
    "Return ONLY valid JSON with these keys: title, body_html.\n".
    "body_html must be valid HTML (no markdown) and include:\n".
    "- Short explanation (2-4 paragraphs)\n".
    "- 5 bullet key points\n".
    "- 3 example sentences with brief notes\n".
    "- 1 mini practice section (3 items) appropriate for the level\n".
    "Keep it clear and student-friendly.\n".
    "If material_type is audio or video, still return text lesson content (script-style is ok).";

  $out = askGeminiJson($prompt, ['temperature'=>0.3, 'max_tokens'=>2048]);

  $title = trim((string)($out['title'] ?? ''));
  $body = (string)($out['body_html'] ?? '');

  if ($title === '' || trim(strip_tags($body)) === '') {
    throw new Exception('Invalid AI response');
  }

  echo json_encode(['ok'=>true, 'title'=>$title, 'body_html'=>$body]);
} catch (Throwable $e) {
  echo json_encode(['ok'=>false, 'error'=>'AI generation failed']);
}
