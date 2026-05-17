<?php
require_once __DIR__ . '/../../includes/rbac.php';
require_once __DIR__ . '/../../includes/csrf.php';

header('Content-Type: application/json; charset=utf-8');
$u = require_role('student');
csrf_check();

$term = trim($_POST['term'] ?? '');
$meaning = trim($_POST['meaning'] ?? '');
$example = trim($_POST['example'] ?? '');
$note = trim($_POST['note'] ?? '');

if ($term === '') {
  echo json_encode(['ok'=>false,'msg'=>'term empty'], JSON_UNESCAPED_UNICODE);
  exit;
}

db()->prepare("INSERT INTO notebook_entries(user_id, term, meaning, note, example) VALUES(?,?,?,?,?)")
  ->execute([$u['id'], $term, $meaning ?: null, $note ?: null, $example ?: null]);

echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE);
