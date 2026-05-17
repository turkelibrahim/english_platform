<?php
require_once __DIR__ . '/../includes/rbac.php';
require_once __DIR__ . '/../includes/csrf.php';

$u = require_role('student');

$type = (string)($_GET['type'] ?? 'all'); // all|lesson|question
$sort = (string)($_GET['sort'] ?? 'new');  // new|old
$q    = trim((string)($_GET['q'] ?? ''));

$order = ($sort === 'old') ? 'ASC' : 'DESC';

// Lessons favorites
$lessons = [];
if ($type === 'all' || $type === 'lesson') {
  $sql = "
    SELECT f.created_at AS fav_created_at,
           l.id, l.title, l.topic, l.level, l.skill, l.material_type, l.difficulty
    FROM favorites f
    JOIN lessons l ON l.id = f.ref_id
    WHERE f.user_id=? AND f.fav_type='lesson'
      AND l.is_active=1
  ";
  $params = [$u['id']];
  if ($q !== '') {
    $sql .= " AND (l.title LIKE ? OR COALESCE(l.topic,'') LIKE ?)";
    $params[] = "%{$q}%";
    $params[] = "%{$q}%";
  }
  $sql .= " ORDER BY f.created_at {$order} LIMIT 200";
  $st = db()->prepare($sql);
  $st->execute($params);
  $lessons = $st->fetchAll();
}

// Questions favorites
$questions = [];
if ($type === 'all' || $type === 'question') {
  $sql = "
    SELECT f.created_at AS fav_created_at,
           q.id, q.prompt, q.topic, q.skill, q.difficulty
    FROM favorites f
    JOIN questions q ON q.id = f.ref_id
    WHERE f.user_id=? AND f.fav_type='question'
      AND q.is_active=1
  ";
  $params = [$u['id']];
  if ($q !== '') {
    $sql .= " AND (q.prompt LIKE ? OR COALESCE(q.topic,'') LIKE ?)";
    $params[] = "%{$q}%";
    $params[] = "%{$q}%";
  }
  $sql .= " ORDER BY f.created_at {$order} LIMIT 200";
  $st = db()->prepare($sql);
  $st->execute($params);
  $questions = $st->fetchAll();
}

function e(?string $s): string { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function short_text(string $s, int $n=140): string {
  $s = trim(preg_replace('/\s+/', ' ', $s));
  return (mb_strlen($s) > $n) ? (mb_substr($s, 0, $n-1).'…') : $s;
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Favorites</title>
  <link rel="stylesheet" href="<?=BASE_URL?>/public/assets/css/app.css">
  <script>const BASE_URL = "<?=BASE_URL?>";</script>
</head>
<body data-theme="<?=e($u['theme'])?>">
<div class="container">
  <?php $navPage="Favorites"; $navActive="favorites"; include __DIR__ . '/../includes/partials/student_nav.php'; ?>

  <div class="card" style="margin-top:18px">
    <div class="h1" style="font-size:18px">My Favorites</div>
<div class="hr"></div>

    <form method="get" class="row" style="gap:10px; flex-wrap:wrap; align-items:end">
      <div style="min-width:180px">
        <div class="muted" style="margin-bottom:6px">Type</div>
        <select class="input" name="type">
          <option value="all" <?= $type==='all'?'selected':'' ?>>All</option>
          <option value="lesson" <?= $type==='lesson'?'selected':'' ?>>Lessons</option>
          <option value="question" <?= $type==='question'?'selected':'' ?>>Questions</option>
        </select>
      </div>

      <div style="min-width:180px">
        <div class="muted" style="margin-bottom:6px">Sort</div>
        <select class="input" name="sort">
          <option value="new" <?= $sort==='new'?'selected':'' ?>>Newest first</option>
          <option value="old" <?= $sort==='old'?'selected':'' ?>>Oldest first</option>
        </select>
      </div>

      <div style="flex:1; min-width:240px">
        <div class="muted" style="margin-bottom:6px">Search (title / prompt / topic)</div>
        <input class="input" name="q" value="<?=e($q)?>" placeholder="e.g., Past Simple" />
      </div>

      <button class="btn primary" type="submit">Apply</button>
      <a class="btn" href="<?=BASE_URL?>/student/favorites.php">Reset</a>
    </form>
  </div>

  <?php if (($type==='all' || $type==='lesson')): ?>
    <div class="card" style="margin-top:18px">
      <div class="row" style="justify-content:space-between; align-items:baseline">
        <div>
          <div class="h1" style="font-size:16px">Favorited Lessons</div>
</div>
        <div class="pill"><?=count($lessons)?> items</div>
      </div>
      <div class="hr"></div>

      <?php if(!$lessons): ?>
        <div class="toast">No favorited lessons found.</div>
      <?php else: ?>
        <div style="display:grid; gap:10px">
          <?php foreach($lessons as $l): ?>
            <div class="card" style="margin:0; box-shadow:none">
              <div class="muted">
                <?=e($l['skill'])?> · <?=e($l['material_type'])?> · diff <?= (int)$l['difficulty'] ?> ·
                Topic: <b><?=e($l['topic'] ?: '—')?></b>
              </div>
              <div style="margin-top:6px; font-weight:900"><?=e($l['title'])?></div>
              <div class="muted" style="margin-top:6px">Saved: <?=e($l['fav_created_at'])?></div>
              <div style="margin-top:10px; display:flex; gap:8px; flex-wrap:wrap">
                <a class="btn primary" href="<?=BASE_URL?>/student/lesson_view.php?id=<?=(int)$l['id']?>">Open</a>
                <button class="btn" onclick="toggleFav('lesson', <?= (int)$l['id'] ?>, this)">Remove</button>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if (($type==='all' || $type==='question')): ?>
    <div class="card" style="margin-top:18px">
      <div class="row" style="justify-content:space-between; align-items:baseline">
        <div>
          <div class="h1" style="font-size:16px">Favorited Questions</div>
</div>
        <div class="pill"><?=count($questions)?> items</div>
      </div>
      <div class="hr"></div>

      <?php if(!$questions): ?>
        <div class="toast">No favorited questions found.</div>
      <?php else: ?>
        <div style="display:grid; gap:10px">
          <?php foreach($questions as $qq): ?>
            <div class="card" style="margin:0; box-shadow:none">
              <div class="muted">
                <?=e($qq['skill'])?> · diff <?= (int)$qq['difficulty'] ?> ·
                Topic: <b><?=e($qq['topic'] ?: '—')?></b>
              </div>
              <div style="margin-top:6px; font-weight:900"><?=e(short_text((string)$qq['prompt'], 160))?></div>
              <div class="muted" style="margin-top:6px">Saved: <?=e($qq['fav_created_at'])?></div>
              <div style="margin-top:10px; display:flex; gap:8px; flex-wrap:wrap">
                <a class="btn primary" href="<?=BASE_URL?>/student/practice_view.php?qid=<?=(int)$qq['id']?>">Open</a>
                <button class="btn" onclick="toggleFav('question', <?= (int)$qq['id'] ?>, this)">Remove</button>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>

<form id="csrfForm" style="display:none">
  <input type="hidden" name="csrf" value="<?=csrf_token()?>">
</form>

<script>
async function toggleFav(type, refId, btn){
  const csrf = document.querySelector('#csrfForm input[name="csrf"]').value;
  const res = await fetch(`${BASE_URL}/student/api/favorite_toggle_ajax.php`,{
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:`csrf=${encodeURIComponent(csrf)}&fav_type=${encodeURIComponent(type)}&ref_id=${encodeURIComponent(refId)}`
  });
  const data = await res.json();
  if(!data.ok){ alert('Action failed'); return; }
  // remove from UI
  const card = btn.closest('.card');
  if(card) card.remove();
}
</script>
</body>
</html>
