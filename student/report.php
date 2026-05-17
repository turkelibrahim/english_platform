<?php
require_once __DIR__ . '/../includes/rbac.php';
require_once __DIR__ . '/../includes/csrf.php';

$u = require_role('student');

$st = db()->prepare("SELECT * FROM reports WHERE reporter_id=? ORDER BY id DESC LIMIT 20");
$st->execute([$u['id']]);
$reports = $st->fetchAll();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Report a Problem</title>
  <link rel="stylesheet" href="<?=BASE_URL?>/public/assets/css/app.css">
</head>
<body data-theme="<?=htmlspecialchars($u['theme'])?>">
<div class="container">
  <?php $navPage="Report"; $navActive="report"; include __DIR__ . '/../includes/partials/student_nav.php'; ?>

  <div class="grid">
    <div class="card">
      <div class="h1">Having an issue?</div>
<div class="hr"></div>

      <form method="post" action="<?=BASE_URL?>/student/api/report_add.php">
        <input type="hidden" name="csrf" value="<?=csrf_token()?>">
        <select class="input" name="category" required>
          <option value="question_error">Wrong/incorrect question</option>
          <option value="audio_problem">Audio / listening issue</option>
          <option value="ui_bug">UI / screen bug</option>
          <option value="account_issue">Account issue</option>
          <option value="other">Other</option>
        </select><br><br>

        <input class="input" name="page" placeholder="Where did it happen? (e.g., placement, dashboard)"><br><br>
        <textarea class="input" name="message" rows="5" placeholder="What happened? What did you expect? (brief)" required></textarea><br><br>

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
        <div class="card" style="box-shadow:none; margin-bottom:10px">
          <div class="muted">
            <?=htmlspecialchars($r['created_at'])?> ·
            Category: <?=htmlspecialchars($r['category'])?> ·
            Status: <b><?=htmlspecialchars($r['status'])?></b>
          </div>
          <?php if($r['page']): ?>
            <div class="muted" style="margin-top:6px">Page: <?=htmlspecialchars($r['page'])?></div>
          <?php endif; ?>
          <div style="margin-top:8px"><?=nl2br(htmlspecialchars($r['message']))?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
</body>
</html>
