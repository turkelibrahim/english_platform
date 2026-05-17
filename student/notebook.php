<?php
require_once __DIR__ . '/../includes/rbac.php';
require_once __DIR__ . '/../includes/csrf.php';

$u = require_role('student');

$err = '';

function e(?string $s): string { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function short_text(string $s, int $n = 64): string {
  $s = trim((string)preg_replace('/\s+/', ' ', $s));
  return (mb_strlen($s) > $n) ? (mb_substr($s, 0, $n - 1) . '…') : $s;
}

// Helpers
function post_str(string $k): string {
  return trim((string)($_POST[$k] ?? ''));
}

// Add
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
  csrf_check();
  $term = post_str('term');
  $meaning = post_str('meaning');
  $example = post_str('example');
  $note = post_str('note');

  if ($term === '') {
    $err = 'Title is required.';
  } else {
    db()->prepare("INSERT INTO notebook_entries(user_id, term, meaning, example, note) VALUES(?,?,?,?,?)")
      ->execute([(int)$u['id'], $term, ($meaning === '' ? null : $meaning), ($example === '' ? null : $example), ($note === '' ? null : $note)]);
    header('Location: ' . BASE_URL . '/student/notebook.php?saved=1');
    exit;
  }
}

// Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
  csrf_check();
  $id = (int)($_POST['id'] ?? 0);
  $term = post_str('term');
  $meaning = post_str('meaning');
  $example = post_str('example');
  $note = post_str('note');

  if ($id <= 0) {
    $err = 'Invalid entry.';
  } elseif ($term === '') {
    $err = 'Title is required.';
  } else {
    db()->prepare("UPDATE notebook_entries SET term=?, meaning=?, example=?, note=? WHERE id=? AND user_id=?")
      ->execute([$term, ($meaning === '' ? null : $meaning), ($example === '' ? null : $example), ($note === '' ? null : $note), $id, (int)$u['id']]);
    header('Location: ' . BASE_URL . '/student/notebook.php?view=' . $id . '&saved=1');
    exit;
  }
}

// Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
  csrf_check();
  $id = (int)($_POST['id'] ?? 0);
  if ($id > 0) {
    db()->prepare("DELETE FROM notebook_entries WHERE id=? AND user_id=?")
      ->execute([$id, (int)$u['id']]);
  }
  header('Location: ' . BASE_URL . '/student/notebook.php');
  exit;
}

$viewId = (int)($_GET['view'] ?? 0);
$editId = (int)($_GET['edit'] ?? 0);

$st = db()->prepare("SELECT id, term, meaning, example, note, created_at FROM notebook_entries WHERE user_id=? ORDER BY id DESC LIMIT 200");
$st->execute([(int)$u['id']]);
$notes = $st->fetchAll();

$active = null;
if ($viewId > 0) {
  $st2 = db()->prepare("SELECT id, term, meaning, example, note, created_at FROM notebook_entries WHERE id=? AND user_id=? LIMIT 1");
  $st2->execute([$viewId, (int)$u['id']]);
  $active = $st2->fetch();
}

$editing = null;
if ($editId > 0) {
  $st3 = db()->prepare("SELECT id, term, meaning, example, note, created_at FROM notebook_entries WHERE id=? AND user_id=? LIMIT 1");
  $st3->execute([$editId, (int)$u['id']]);
  $editing = $st3->fetch();
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Notebook</title>
  <link rel="stylesheet" href="<?=BASE_URL?>/public/assets/css/app.css">
</head>
<body data-theme="<?=e($u['theme'])?>">
<div class="container">
  <?php $navPage = "Notebook"; $navActive = "notebook"; include __DIR__ . '/../includes/partials/student_nav.php'; ?>

  <div class="nb-wrap" style="margin-top:18px">
    <div class="nb-book">
      <!-- Left page: index -->
      <div class="nb-page nb-left">
        <div class="nb-title">Index</div>
        <div class="nb-sub">Saved words & notes</div>

        <div class="nb-list">
          <?php if (!$notes): ?>
            <div class="nb-empty">No notes yet. Tap a word in a lesson/question and add it to your Notebook ✨</div>
          <?php else: ?>
            <?php foreach ($notes as $n):
              $isActive = ($active && (int)$active['id'] === (int)$n['id']);
              $isEditing = ($editing && (int)$editing['id'] === (int)$n['id']);
            ?>
              <a class="nb-item <?= ($isActive || $isEditing) ? 'is-active' : '' ?>" href="<?=BASE_URL?>/student/notebook.php?view=<?=(int)$n['id']?>">
                <div class="nb-item-term"><?=e(short_text((string)$n['term'], 26))?></div>
                <div class="nb-item-meta"><?=e(date('Y-m-d', strtotime((string)$n['created_at'])))?></div>
              </a>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <div class="nb-actions">
          <a class="btn" href="<?=BASE_URL?>/student/notebook.php">New Page +</a>
        </div>
      </div>

      <!-- Spine -->
      <div class="nb-spine" aria-hidden="true"></div>

      <!-- Right page: editor/view -->
      <div class="nb-page nb-right">
        <?php if ($err): ?>
          <div class="toast" style="margin-bottom:12px"><?=e($err)?></div>
        <?php elseif (isset($_GET['saved'])): ?>
          <div class="toast" style="margin-bottom:12px">Saved ✅</div>
        <?php endif; ?>

        <?php if ($editing): ?>
          <div class="nb-head">
            <div>
              <div class="nb-term">Edit: <?=e($editing['term'])?></div>
              <div class="nb-meta"><?=e($editing['created_at'])?></div>
            </div>
            <div style="display:flex; gap:8px;">
              <a class="btn" href="<?=BASE_URL?>/student/notebook.php?view=<?=(int)$editing['id']?>">Cancel</a>
              <form method="post" onsubmit="return confirm('Delete this note?')" style="margin:0">
                <input type="hidden" name="csrf" value="<?=csrf_token()?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$editing['id'] ?>">
                <button class="btn" type="submit">Delete</button>
              </form>
            </div>
          </div>

          <form method="post" class="nb-form" style="margin-top:14px">
            <input type="hidden" name="csrf" value="<?=csrf_token()?>">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?= (int)$editing['id'] ?>">

            <div>
              <div class="nb-label" style="margin-bottom:6px">Word / Title</div>
              <input class="input" name="term" required value="<?=e($_POST['term'] ?? $editing['term'])?>">
            </div>

            <div>
              <div class="nb-label" style="margin-bottom:6px">Meaning</div>
              <textarea class="input" name="meaning" rows="3" placeholder="Short meaning..."><?=e($_POST['meaning'] ?? ($editing['meaning'] ?? ''))?></textarea>
            </div>

            <div>
              <div class="nb-label" style="margin-bottom:6px">Example</div>
              <textarea class="input" name="example" rows="3" placeholder="Example sentence..."><?=e($_POST['example'] ?? ($editing['example'] ?? ''))?></textarea>
            </div>

            <div>
              <div class="nb-label" style="margin-bottom:6px">Where it was used</div>
              <textarea class="input" name="note" rows="2" placeholder="Lesson / Question / Context..."><?=e($_POST['note'] ?? ($editing['note'] ?? ''))?></textarea>
            </div>

            <div class="nb-actions" style="justify-content:flex-end">
              <button class="btn primary" type="submit">Save Changes 💾</button>
            </div>
          </form>

        <?php elseif ($active): ?>
          <div class="nb-head">
            <div>
              <div class="nb-term"><?=e($active['term'])?></div>
              <div class="nb-meta"><?=e($active['created_at'])?></div>
            </div>
            <div style="display:flex; gap:8px;">
              <a class="btn" href="<?=BASE_URL?>/student/notebook.php?edit=<?=(int)$active['id']?>">Edit</a>
              <form method="post" onsubmit="return confirm('Delete this note?')" style="margin:0">
                <input type="hidden" name="csrf" value="<?=csrf_token()?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$active['id'] ?>">
                <button class="btn" type="submit">Delete</button>
              </form>
            </div>
          </div>

          <div class="nb-section">
            <div class="nb-label">Meaning</div>
            <div class="nb-text"><?=e($active['meaning'] ?: '—')?></div>
          </div>
          <div class="nb-section">
            <div class="nb-label">Example</div>
            <div class="nb-text"><?=e($active['example'] ?: '—')?></div>
          </div>
          <div class="nb-section">
            <div class="nb-label">Where it was used</div>
            <div class="nb-text"><?=e($active['note'] ?: '—')?></div>
          </div>

        <?php else: ?>
          <div class="nb-head">
            <div>
              <div class="nb-term">New entry</div>
              <div class="nb-meta">Add a word/title + details</div>
            </div>
          </div>

          <form method="post" class="nb-form" style="margin-top:14px">
            <input type="hidden" name="csrf" value="<?=csrf_token()?>">
            <input type="hidden" name="action" value="add">

            <div>
              <div class="nb-label" style="margin-bottom:6px">Word / Title</div>
              <input class="input" name="term" required placeholder="e.g., subject" value="<?=e($_POST['term'] ?? '')?>">
            </div>

            <div>
              <div class="nb-label" style="margin-bottom:6px">Meaning</div>
              <textarea class="input" name="meaning" rows="3" placeholder="Short meaning..."><?=e($_POST['meaning'] ?? '')?></textarea>
            </div>

            <div>
              <div class="nb-label" style="margin-bottom:6px">Example</div>
              <textarea class="input" name="example" rows="3" placeholder="Example sentence..."><?=e($_POST['example'] ?? '')?></textarea>
            </div>

            <div>
              <div class="nb-label" style="margin-bottom:6px">Where it was used</div>
              <textarea class="input" name="note" rows="2" placeholder="Lesson / Question / Context..."><?=e($_POST['note'] ?? '')?></textarea>
            </div>

            <div class="nb-actions" style="justify-content:flex-end">
              <button class="btn primary" type="submit">Save Entry 💾</button>
            </div>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>

</div>
</body>
</html>
