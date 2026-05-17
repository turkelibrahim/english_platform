<?php
require_once __DIR__ . '/../../includes/rbac.php';
require_once __DIR__ . '/../../includes/csrf.php';

$u = require_role('student');
csrf_check();

$category = trim($_POST['category'] ?? 'other');
$page = trim($_POST['page'] ?? '');
$message = trim($_POST['message'] ?? '');

if ($message === '') {
  header("Location: ".BASE_URL."/student/report.php");
  exit;
}

$st = db()->prepare("
  INSERT INTO reports(reporter_id, role, category, page, message)
  VALUES(?, 'student', ?, ?, ?)
");
$st->execute([$u['id'], $category, $page ?: null, $message]);

header("Location: ".BASE_URL."/student/report.php");
