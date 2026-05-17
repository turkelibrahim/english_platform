<?php
require_once __DIR__ . '/../includes/rbac.php';

// Question bank management removed: admins should create questions via Tests.
require_role('admin');
header('Location: '.BASE_URL.'/admin/tests.php');
exit;
