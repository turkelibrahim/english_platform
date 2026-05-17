<?php
require_once __DIR__ . '/../../includes/rbac.php';
require_once __DIR__ . '/../../includes/csrf.php';

$u = require_role('student');
csrf_check();

$type = $_POST['fav_type'] ?? 'lesson';
$ref  = (int)($_POST['ref_id'] ?? 0);

if (!in_array($type, ['lesson','question'], true) || $ref <= 0) {
  header("Location: ".BASE_URL."/student/favorites.php");
  exit;
}

$st = db()->prepare("INSERT IGNORE INTO favorites(user_id, fav_type, ref_id) VALUES(?,?,?)");
$st->execute([$u['id'], $type, $ref]);

header("Location: ".BASE_URL."/student/favorites.php");
