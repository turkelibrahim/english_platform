<?php
require_once __DIR__ . '/../includes/rbac.php';
require_once __DIR__ . '/../includes/csrf.php';

$u = require_role('admin');
$ok = $err = '';
$editId = (int)($_GET['edit'] ?? 0);

function fetch_quote(int $id): ?array {
  $st = db()->prepare("SELECT * FROM quotes WHERE id=?");
  $st->execute([$id]);
  $r = $st->fetch();
  return $r ?: null;
}

$edit = $editId ? fetch_quote($editId) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $action = $_POST['action'] ?? '';

  if ($action === 'add') {
    $qt = trim($_POST['quote_text'] ?? '');
    $au = trim($_POST['author'] ?? '');
    if ($qt === '') {
      $err = "Quote text is required.";
    } else {
      db()->prepare("INSERT INTO quotes(quote_text,author) VALUES(?,?)")->execute([$qt, $au ?: null]);
      $ok = "Quote added.";
    }
  }

  if ($action === 'update') {
    $id = (int)($_POST['id'] ?? 0);
    $qt = trim($_POST['quote_text'] ?? '');
    $au = trim($_POST['author'] ?? '');
    if (!$id || $qt === '') {
      $err = "Invalid update.";
    } else {
      db()->prepare("UPDATE quotes SET quote_text=?, author=? WHERE id=?")->execute([$qt, $au ?: null, $id]);
      $ok = "Quote updated.";
      $editId = 0;
      $edit = null;
    }
  }

  if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id) {
      db()->prepare("DELETE FROM quotes WHERE id=?")->execute([$id]);
      $ok = "Quote deleted.";
      if ($editId === $id) { $editId = 0; $edit = null; }
    }
  }
}

$quotes = db()->query("SELECT * FROM quotes ORDER BY id ASC")->fetchAll();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Admin · Quotes</title>
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
      <div class="h1"><?= $edit ? 'Edit Quote' : 'Add Quote' ?></div>
      <div class="muted">Günün sözü pop-up burada tanımlanan sözlerden sırayla seçilir.</div>
      <div class="hr"></div>

      <?php if($err): ?><div class="toast"><?=$err?></div><?php endif; ?>
      <?php if($ok): ?><div class="toast"><?=$ok?></div><?php endif; ?>

      <form method="post">
        <input type="hidden" name="csrf" value="<?=csrf_token()?>">
        <input type="hidden" name="action" value="<?= $edit ? 'update' : 'add' ?>">
        <?php if($edit): ?><input type="hidden" name="id" value="<?=$edit['id']?>"><?php endif; ?>

        <label class="label">Quote text</label>
        <textarea class="input" name="quote_text" rows="4" required><?=htmlspecialchars($edit['quote_text'] ?? '')?></textarea>

        <label class="label">Author (optional)</label>
        <input class="input" name="author" value="<?=htmlspecialchars($edit['author'] ?? '')?>"/>

        <div class="row" style="margin-top:10px;">
          <button class="btn" type="submit"><?= $edit ? 'Save' : 'Add' ?></button>
          <?php if($edit): ?>
            <a class="btn" href="<?=BASE_URL?>/admin/quotes.php">Cancel</a>
          <?php endif; ?>
        </div>
      </form>
    </div>

    <div class="card">
      <div class="h1">All Quotes (<?=count($quotes)?>)</div>
      <div class="muted">ID sırasına göre döner (sequential).</div>
      <div class="hr"></div>

      <?php foreach($quotes as $q): ?>
        <div class="card" style="padding:14px;">
          <div style="font-weight:800;">#<?=$q['id']?> · <?php $preview = $q['quote_text']; if (function_exists('mb_strimwidth')) { $preview = mb_strimwidth($preview,0,90,'…','UTF-8'); } else { $preview = substr($preview,0,90).(strlen($preview)>90?'…':''); } ?><?=htmlspecialchars($preview)?></div>
          <div class="muted"><?=htmlspecialchars($q['author'] ?? '')?></div>
          <div class="row" style="margin-top:8px;">
            <a class="btn" href="<?=BASE_URL?>/admin/quotes.php?edit=<?=$q['id']?>">Edit</a>
            <form method="post" onsubmit="return confirm('Delete this quote?')" style="display:inline;">
              <input type="hidden" name="csrf" value="<?=csrf_token()?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?=$q['id']?>">
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
