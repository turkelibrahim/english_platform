<?php
require_once __DIR__ . '/../includes/rbac.php';
require_once __DIR__ . '/../includes/csrf.php';

$u = require_role('student');
if ((int)$u['placement_completed'] === 0) {
  header("Location: ".BASE_URL."/student/placement.php");
  exit;
}

$topic = trim((string)($_GET['topic'] ?? ''));
$skill = trim((string)($_GET['skill'] ?? ''));
$sort = (string)($_GET['sort'] ?? 'new');
$q = trim((string)($_GET['q'] ?? ''));

$order = ($sort === 'old') ? 'ASC' : 'DESC';

// Topics list
$topicSt = db()->query("SELECT DISTINCT topic FROM lessons WHERE is_active=1 AND (topic IS NOT NULL AND topic<>'') ORDER BY topic ASC");
$topics = array_map(fn($r)=>$r['topic'], $topicSt->fetchAll() ?: []);

// Build query
$sql = "
  SELECT l.*,
    EXISTS(
      SELECT 1 FROM favorites f
      WHERE f.user_id=? AND f.fav_type='lesson' AND f.ref_id=l.id
    ) AS is_fav
  FROM lessons l
  WHERE l.is_active=1
";
$params = [$u['id']];

if ($topic !== '') {
  if ($topic === '__none__') {
    $sql .= " AND (l.topic IS NULL OR l.topic='')";
  } else {
    $sql .= " AND l.topic=?";
    $params[] = $topic;
  }
}
if ($skill !== '') {
  $sql .= " AND l.skill=?";
  $params[] = $skill;
}
if ($q !== '') {
  $sql .= " AND (l.title LIKE ? OR COALESCE(l.topic,'') LIKE ?)";
  $params[] = "%{$q}%";
  $params[] = "%{$q}%";
}

$sql .= " ORDER BY l.created_at {$order} LIMIT 200";
$st = db()->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

function e(?string $s): string { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Lessons</title>
  <link rel="stylesheet" href="<?=BASE_URL?>/public/assets/css/app.css">
  <script>const BASE_URL = "<?=BASE_URL?>";</script>
</head>
<body data-theme="<?=e($u['theme'])?>">
<div class="container">
  <?php $navPage="Lessons"; $navActive="lessons"; include __DIR__ . '/../includes/partials/student_nav.php'; ?>

  <div class="card" style="margin-top:18px">
    <div class="h1" style="font-size:18px">Lesson Library</div>
<div class="hr"></div>

    <form method="get" class="row" style="gap:10px; flex-wrap:wrap; align-items:end">
      <div style="min-width:220px">
        <div class="muted" style="margin-bottom:6px">Topic</div>
        <select class="input" name="topic">
          <option value="" <?= $topic===''?'selected':'' ?>>All topics</option>
          <option value="__none__" <?= $topic==='__none__'?'selected':'' ?>>(No topic)</option>
          <?php foreach($topics as $t): ?>
            <option value="<?=e($t)?>" <?= $topic===$t?'selected':'' ?>><?=e($t)?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div style="min-width:180px">
        <div class="muted" style="margin-bottom:6px">Skill</div>
        <select class="input" name="skill">
          <option value="" <?= $skill===''?'selected':'' ?>>All skills</option>
          <option value="vocab" <?= $skill==='vocab'?'selected':'' ?>>Vocabulary</option>
          <option value="grammar" <?= $skill==='grammar'?'selected':'' ?>>Grammar</option>
          <option value="reading" <?= $skill==='reading'?'selected':'' ?>>Reading</option>
          <option value="listening" <?= $skill==='listening'?'selected':'' ?>>Listening</option>
          <option value="writing" <?= $skill==='writing'?'selected':'' ?>>Writing</option>
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
        <div class="muted" style="margin-bottom:6px">Search</div>
        <input class="input" name="q" value="<?=e($q)?>" placeholder="Search title or topic" />
      </div>

      <button class="btn primary" type="submit">Apply</button>
      <a class="btn" href="<?=BASE_URL?>/student/lessons.php">Reset</a>
    </form>
  </div>

  <div class="card" style="margin-top:18px">
    <div class="row" style="justify-content:space-between; align-items:baseline">
      <div>
        <div class="h1" style="font-size:16px">Lessons</div>
</div>
      <div class="pill"><?=count($rows)?> shown</div>
    </div>
    <div class="hr"></div>

    <?php if(!$rows): ?>
      <div class="toast">No lessons match your filters.</div>
    <?php else: ?>
      <div style="display:grid; gap:10px">
        <?php foreach($rows as $l): ?>
          <div class="card" style="margin:0; box-shadow:none">
            <div class="muted">
              <?=e($l['skill'])?> · <?=e($l['material_type'])?> · diff <?= (int)$l['difficulty'] ?> ·
              Topic: <b><?=e($l['topic'] ?: '—')?></b> · <?=e($l['created_at'])?>
            </div>
            <div style="margin-top:6px; font-weight:900"><?=e($l['title'])?></div>
            <div style="margin-top:10px; display:flex; gap:8px; flex-wrap:wrap">
              <a class="btn primary" href="<?=BASE_URL?>/student/lesson_view.php?id=<?=(int)$l['id']?>">Open</a>
              <button class="btn" onclick="toggleFav(<?= (int)$l['id'] ?>, this)">
                <?= ((int)$l['is_fav']===1) ? '★ Saved' : '☆ Save' ?>
              </button>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<form id="csrfForm" style="display:none">
  <input type="hidden" name="csrf" value="<?=csrf_token()?>">
</form>

<script>
async function toggleFav(lessonId, btn){
  const csrf = document.querySelector('#csrfForm input[name="csrf"]').value;
  const res = await fetch(`${BASE_URL}/student/api/favorite_toggle_ajax.php`,{
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:`csrf=${encodeURIComponent(csrf)}&fav_type=lesson&ref_id=${encodeURIComponent(lessonId)}`
  });
  const data = await res.json();
  if(!data.ok){ alert('Action failed'); return; }
  btn.textContent = (data.state === 'added') ? '★ Saved' : '☆ Save';
}
</script>
</body>
</html>
