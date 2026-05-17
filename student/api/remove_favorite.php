<?php
require_once __DIR__ . '/../../includes/rbac.php';
require_once __DIR__ . '/../../includes/csrf.php';

$u = require_role('student');
csrf_check();

$favId = (int)($_POST['fav_id'] ?? 0);
if ($favId) {
  $st = db()->prepare("DELETE FROM favorites WHERE id=? AND user_id=?");
  $st->execute([$favId, $u['id']]);
}

header("Location: ".BASE_URL."/student/favorites.php");
