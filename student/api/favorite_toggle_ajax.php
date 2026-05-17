<?php
require_once __DIR__ . '/../../includes/rbac.php';
require_once __DIR__ . '/../../includes/csrf.php';

header('Content-Type: application/json; charset=utf-8');
$u = require_role('student');

// CSRF zorunlu
csrf_check();

$type = $_POST['fav_type'] ?? '';
$ref  = (int)($_POST['ref_id'] ?? 0);

if (!in_array($type, ['lesson','question'], true) || $ref <= 0) {
  echo json_encode(['ok'=>false, 'msg'=>'invalid'], JSON_UNESCAPED_UNICODE);
  exit;
}

// var mı?
$st = db()->prepare("SELECT id FROM favorites WHERE user_id=? AND fav_type=? AND ref_id=? LIMIT 1");
$st->execute([$u['id'], $type, $ref]);
$existing = $st->fetchColumn();

if ($existing) {
  db()->prepare("DELETE FROM favorites WHERE id=? AND user_id=?")->execute([(int)$existing, $u['id']]);
  echo json_encode(['ok'=>true,'state'=>'removed'], JSON_UNESCAPED_UNICODE);
} else {
  db()->prepare("INSERT INTO favorites(user_id,fav_type,ref_id) VALUES(?,?,?)")->execute([$u['id'],$type,$ref]);
  echo json_encode(['ok'=>true,'state'=>'added'], JSON_UNESCAPED_UNICODE);
}
