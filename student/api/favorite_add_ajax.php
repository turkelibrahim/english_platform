<?php
require_once __DIR__ . '/../../includes/rbac.php';

header('Content-Type: application/json; charset=utf-8');
$u = require_role('student');

$type = $_POST['fav_type'] ?? 'question';
$ref  = (int)($_POST['ref_id'] ?? 0);

if (!in_array($type, ['lesson','question'], true) || $ref <= 0) {
  echo json_encode(['ok'=>false], JSON_UNESCAPED_UNICODE);
  exit;
}

db()->prepare("INSERT IGNORE INTO favorites(user_id, fav_type, ref_id) VALUES(?,?,?)")
  ->execute([$u['id'], $type, $ref]);

echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE);
