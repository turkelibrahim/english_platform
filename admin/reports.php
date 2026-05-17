<?php
require_once __DIR__ . '/../includes/rbac.php';

// Reports inbox removed: student-submitted reports should not be visible in the admin account.
// Admins can still submit/view their own reports at /admin/report.php.
require_role('admin');
header('Location: '.BASE_URL.'/admin/report.php');
exit;
