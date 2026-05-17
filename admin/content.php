<?php
require_once __DIR__ . '/../includes/rbac.php';

$u = require_role('admin');
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Admin · Content</title>
  <link rel="stylesheet" href="<?=BASE_URL?>/public/assets/css/app.css">
</head>
<body data-theme="<?=htmlspecialchars($u['theme'] ?? 'light')?>">
<div class="container">
  <?php $navPage="Content"; $navActive="content"; include __DIR__ . '/../includes/partials/admin_nav.php'; ?>

  <div class="card">
    <div class="h1">Content Management</div>
<div class="hr"></div>

    <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));">
      <div class="card">
        <div class="h2">Lessons</div>
<div class="hr"></div>
        <a class="btn" href="<?=BASE_URL?>/admin/lessons.php">Manage Lessons</a>
      </div>
      <div class="card">
        <div class="h2">Quotes</div>
<div class="hr"></div>
        <a class="btn" href="<?=BASE_URL?>/admin/quotes.php">Manage Quotes</a>
      </div>

      <div class="card">
        <div class="h2">Badges</div>
<div class="hr"></div>
        <a class="btn" href="<?=BASE_URL?>/admin/badges.php">Manage Badges</a>
      </div>
    </div>
  </div>
</div>
</body>
</html>
