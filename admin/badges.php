<?php
require_once __DIR__ . '/../includes/rbac.php';
require_once __DIR__ . '/../includes/csrf.php';

$u = require_role('admin');
$ok = $err = '';
$editId = (int)($_GET['edit'] ?? 0);

function fetch_badge(int $id): ?array {
  $st = db()->prepare("SELECT * FROM badges WHERE id=?");
  $st->execute([$id]);
  $r = $st->fetch();
  return $r ?: null;
}

$edit = $editId ? fetch_badge($editId) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $action = $_POST['action'] ?? '';

  $code = trim($_POST['code'] ?? '');
  $title = trim($_POST['title'] ?? '');
  $desc = trim($_POST['description'] ?? '');
  $pts  = (int)($_POST['points_required'] ?? 0);

  if ($action === 'add') {
    if ($code==='' || $title==='' || $desc==='') {
      $err = "Code, title and description are required.";
    } else {
      try {
        db()->prepare("INSERT INTO badges(code,title,description,points_required) VALUES(?,?,?,?)")
          ->execute([$code, $title, $desc, $pts]);
        $ok = "Badge added.";
      } catch (PDOException $e) {
        $err = "Could not add badge (code must be unique).";
      }
    }
  }

  if ($action === 'update') {
    $id = (int)($_POST['id'] ?? 0);
    if (!$id || $code==='' || $title==='' || $desc==='') {
      $err = "Invalid update.";
    } else {
      try {
        db()->prepare("UPDATE badges SET code=?, title=?, description=?, points_required=? WHERE id=?")
          ->execute([$code, $title, $desc, $pts, $id]);
        $ok = "Badge updated.";
        $editId = 0;
        $edit = null;
      } catch (PDOException $e) {
        $err = "Could not update badge (code must be unique).";
      }
    }
  }

  if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id) {
      // remove mappings too
      db()->prepare("DELETE FROM user_badges WHERE badge_id=?")->execute([$id]);
      db()->prepare("DELETE FROM badges WHERE id=?")->execute([$id]);
      $ok = "Badge deleted.";
      if ($editId === $id) { $editId=0; $edit=null; }
    }
  }
}

$badges = db()->query("SELECT * FROM badges ORDER BY points_required ASC")->fetchAll();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Admin · Badges</title>
  <link rel="stylesheet" href="<?=BASE_URL?>/public/assets/css/app.css">
</head>
<body data-theme="<?=htmlspecialchars($u['theme'] ?? 'light')?>">
<div class="container">
  <div class="nav">
    <div class="brand"><div class="logo"></div> Admin</div>
    <div class="nav-right">
      <a class="btn" href="<?=BASE_URL?>/admin/dashboard.php">Dashboard</a>
      <a class="btn" href="<?=BASE_URL?>/admin/content.php">Content</a>
      <a class="btn" href="<?=BASE_URL?>/admin/report.php">Report issue</a>
      <a class="btn" href="<?=BASE_URL?>/admin/profile.php">Profile</a>
      <a class="btn" href="<?=BASE_URL?>/public/logout.php">Logout</a>
    </div>
  </div>

  <div class="grid">
    <div class="card">
      <div class="h1"><?= $edit ? 'Edit Badge' : 'Add Badge' ?></div>
      <div class="muted">Rozetler, öğrencinin puanına göre otomatik verilir (practice/placement sonrası kontrol edilir).</div>
      <div class="hr"></div>

      <?php if($err): ?><div class="toast"><?=$err?></div><?php endif; ?>
      <?php if($ok): ?><div class="toast"><?=$ok?></div><?php endif; ?>

      <form method="post">
        <input type="hidden" name="csrf" value="<?=csrf_token()?>">
        <input type="hidden" name="action" value="<?= $edit ? 'update' : 'add' ?>">
        <?php if($edit): ?><input type="hidden" name="id" value="<?=$edit['id']?>"><?php endif; ?>

        <label class="label">Code (unique)</label>
        <input class="input" name="code" required value="<?=htmlspecialchars($edit['code'] ?? '')?>" placeholder="e.g. starter"/>

        <label class="label">Title</label>
        <input class="input" name="title" required value="<?=htmlspecialchars($edit['title'] ?? '')?>" placeholder="e.g. Starter"/>

        <label class="label">Description</label>
        <input class="input" name="description" required value="<?=htmlspecialchars($edit['description'] ?? '')?>" placeholder="e.g. Reach 50 points."/>

        <label class="label">Points required</label>
        <input class="input" name="points_required" type="number" min="0" value="<?=htmlspecialchars($edit['points_required'] ?? 0)?>"/>

        <div class="row" style="margin-top:10px;">
          <button class="btn" type="submit"><?= $edit ? 'Save' : 'Add' ?></button>
          <?php if($edit): ?>
            <a class="btn" href="<?=BASE_URL?>/admin/badges.php">Cancel</a>
          <?php endif; ?>
        </div>
      </form>
    </div>

    <div class="card">
      <div class="h1">All Badges (<?=count($badges)?>)</div>
      <div class="muted">Minimum puana göre sıralı.</div>
      <div class="hr"></div>

      <?php foreach($badges as $b): ?>
        <div class="card" style="padding:14px;">
          <div style="font-weight:900;"><?=htmlspecialchars($b['title'])?> <span class="muted">(<?=$b['code']?>)</span></div>
          <div class="muted">Requires: <?=$b['points_required']?> points</div>
          <div style="margin-top:6px;"><?=htmlspecialchars($b['description'])?></div>
          <div class="row" style="margin-top:8px;">
            <a class="btn" href="<?=BASE_URL?>/admin/badges.php?edit=<?=$b['id']?>">Edit</a>
            <form method="post" onsubmit="return confirm('Delete this badge?')">
              <input type="hidden" name="csrf" value="<?=csrf_token()?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?=$b['id']?>">
              <button class="btn" type="submit">Delete</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
</body>
</html>
