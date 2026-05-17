<?php
require_once __DIR__ . '/../includes/rbac.php';
require_once __DIR__ . '/../includes/csrf.php';

$u = require_role('admin');

$st = db()->prepare("SELECT * FROM reports WHERE reporter_id=? ORDER BY id DESC LIMIT 20");
$st->execute([$u['id']]);
$reports = $st->fetchAll();

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Admin · Report a Problem</title>
  <link rel="stylesheet" href="<?=BASE_URL?>/public/assets/css/app.css">
</head>
<body data-theme="<?=e($u['theme'] ?? 'light')?>">
<div class="container">
  <?php $navPage="Report"; $navActive="report"; include __DIR__ . '/../includes/partials/admin_nav.php'; ?>

  <div class="grid" style="margin-top:18px">
    <div class="card">
      <div class="h1">Report an issue</div>
<div class="hr"></div>

      <form method="post" action="<?=BASE_URL?>/admin/api/report_add.php">
        <input type="hidden" name="csrf" value="<?=csrf_token()?>">
        <select class="input" name="category" required>
          <option value="ui_bug">UI / screen bug</option>
          <option value="student_issue">Student issue</option>
          <option value="question_error">Wrong/incorrect question</option>
          <option value="system_error">System error</option>
          <option value="other">Other</option>
        </select><br><br>

        <input class="input" name="page" placeholder="Where did it happen? (e.g., dashboard, students)"><br><br>

        <textarea class="input" name="message" rows="6" placeholder="What happened? What did you expect? (brief)" required></textarea><br><br>

        <button class="btn primary" style="width:100%">Send</button>
      </form>
    </div>

    <div class="card">
      <div class="h1">Your submitted reports</div>
      <div class="muted">Last 20 entries</div>
      <div class="hr"></div>

      <?php if(!$reports): ?>
        <div class="toast">No reports yet.</div>
      <?php endif; ?>

      <?php foreach($reports as $r): ?>
        <div class="card" style="margin:0; box-shadow:none">
          <div class="row" style="justify-content:space-between; gap:12px; align-items:baseline">
            <div style="font-weight:900"><?=e($r['category'])?></div>
            <div class="muted"><?=e($r['created_at'])?></div>
          </div>
          <div class="muted" style="margin-top:6px">
            Status: <b><?=e($r['status'])?></b>
          </div>
          <?php if($r['page']): ?>
            <div class="muted" style="margin-top:6px">Page: <?=e($r['page'])?></div>
          <?php endif; ?>
          <div style="margin-top:8px"><?=nl2br(e($r['message']))?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
</body>
</html>
