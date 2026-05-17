<?php
require_once __DIR__ . '/../includes/rbac.php';
require_once __DIR__ . '/../includes/tasks.php';

$u = require_role('student');
refresh_tasks_for_user((int)$u['id'], 3);
header('Location: '.BASE_URL.'/student/dashboard.php?tasks_refreshed=1');
exit;
