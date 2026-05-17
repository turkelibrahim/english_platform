<?php
require_once __DIR__ . '/../includes/rbac.php';

// Question bank management removed: admins should create questions via Tests (AI-generated or manual within a test).
require_role('admin');
header('Location: '.BASE_URL.'/admin/tests.php');
exit;
