<?php
require_once __DIR__ . '/../../includes/rbac.php';
require_once __DIR__ . '/../../includes/csrf.php';

$u = require_role('student');
csrf_check();

$id = (int)($_POST['id'] ?? 0);
if ($id) {
  $st = db()->prepare("DELETE FROM notebook_entries WHERE id=? AND user_id=?");
  $st->execute([$id, $u['id']]);
}

header("Location: ".BASE_URL."/student/notebook.php");
