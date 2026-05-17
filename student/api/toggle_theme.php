<?php
require_once __DIR__ . '/../../includes/auth.php';
$u = require_login();

$theme = $_POST['theme'] ?? 'light';
if (!in_array($theme, ['light','dark'], true)) $theme = 'light';

db()->prepare("UPDATE users SET theme=? WHERE id=?")->execute([$theme, $u['id']]);
echo "ok";
