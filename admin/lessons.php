<?php
require_once __DIR__ . '/../includes/rbac.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/topic_lists.php';

$u = require_role('admin');

// Topic list: show a curated list of Grammar topics (avoid Daily Routine / general themes)
$topics = grammar_topics();
sort($topics, SORT_NATURAL | SORT_FLAG_CASE);

$editing = false;
$lesson = null;
$editId = (int)($_GET['edit'] ?? 0);
if($editId>0){
  $st = db()->prepare("SELECT * FROM lessons WHERE id=? LIMIT 1");
  $st->execute([$editId]);
  $lesson = $st->fetch();
  if($lesson){ $editing = true; }
}

// If editing an older lesson whose topic is not in the curated list, keep it visible in the dropdown.
if ($editing) {
  $cur = trim((string)($lesson['topic'] ?? ''));
  if ($cur !== '' && !in_array($cur, $topics, true)) {
    array_unshift($topics, $cur);
  }
}

$ok = $err = '';

if($_SERVER['REQUEST_METHOD']==='POST'){
  csrf_check();
  $action = $_POST['action'] ?? '';

  if($action === 'delete'){
    $id = (int)($_POST['id'] ?? 0);
    if($id>0){
      db()->prepare("DELETE FROM lessons WHERE id=?")->execute([$id]);
      $ok = 'Lesson deleted.';
      $editing = false;
      $lesson = null;
    }
  }

  if($action === 'add' || $action === 'update'){
    $id = (int)($_POST['id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $topic = trim($_POST['topic'] ?? '');
    $level = trim($_POST['level'] ?? 'A1');
    $skill = trim($_POST['skill'] ?? 'reading');
    $material = trim($_POST['material_type'] ?? 'text');
    $body = trim($_POST['body_html'] ?? '');
    $difficulty = (int)($_POST['difficulty'] ?? 1);
    $isActive = (int)($_POST['is_active'] ?? 1);

    if($title==='' || $topic==='' || $body===''){
      $err = 'Title, topic and content are required.';
    } else {
      try{
        if($difficulty < 1) $difficulty = 1;
        if($difficulty > 5) $difficulty = 5;
        if(!in_array($topic, $topics, true)){
          // If admin posted a topic not in list (e.g., stale form), accept but keep safe.
          // This keeps the dropdown rule in UI while not breaking requests.
        }

        if($action === 'add'){
          db()->prepare(
            "INSERT INTO lessons (title, topic, level, skill, material_type, body_html, difficulty, is_active)\n".
            "VALUES (?,?,?,?,?,?,?,?)"
          )->execute([$title, $topic, $level, $skill, $material, $body, $difficulty, $isActive]);
          $ok = 'Lesson added.';
        } else {
          if($id<=0) throw new Exception('Missing id');
          db()->prepare(
            "UPDATE lessons SET title=?, topic=?, level=?, skill=?, material_type=?, body_html=?, difficulty=?, is_active=? WHERE id=?"
          )->execute([$title, $topic, $level, $skill, $material, $body, $difficulty, $isActive, $id]);
          $ok = 'Lesson updated.';
          header('Location: '.BASE_URL.'/admin/lessons.php');
          exit;
        }
      } catch(Throwable $e){
        $err = 'Save failed.';
      }
    }
  }
}

// Filters for the list
$fLevel = trim((string)($_GET['lv'] ?? ''));
if(!in_array($fLevel, ['', 'A1','A2','B1','B2','C1'], true)) { $fLevel = ''; }

$where = [];
$params = [];
if($fLevel !== ''){
  $where[] = 'level = ?';
  $params[] = $fLevel;
}

$sql = "SELECT id, title, topic, level, skill, material_type, difficulty, is_active, created_at FROM lessons";
if($where){
  $sql .= " WHERE ".implode(' AND ', $where);
}
$sql .= " ORDER BY created_at DESC LIMIT 200";

$stList = db()->prepare($sql);
$stList->execute($params);
$list = $stList->fetchAll();

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Admin · Lessons</title>
  <link rel="stylesheet" href="<?=BASE_URL?>/public/assets/css/app.css">
</head>
<body data-theme="<?=e($u['theme'] ?? 'light')?>">
<div class="container">
  <?php $navPage="Lessons"; $navActive="lessons"; include __DIR__ . '/../includes/partials/admin_nav.php'; ?>

  <div class="card" style="margin-top:18px">
    <div class="h1"><?= $editing ? 'Edit lesson' : 'Add lesson' ?></div>
    <div class="muted">Tip: Use <b>Generate with Gemini</b> to pull a topic explanation, then review and Save.</div>
    <div class="hr"></div>

    <?php if($err): ?><div class="toast"><?=e($err)?></div><div class="hr"></div><?php endif; ?>
    <?php if($ok): ?><div class="toast"><?=e($ok)?></div><div class="hr"></div><?php endif; ?>

    <form method="post" id="lessonForm">
      <input type="hidden" name="csrf" value="<?=csrf_token()?>">
      <input type="hidden" name="action" value="<?= $editing ? 'update' : 'add' ?>">
      <input type="hidden" name="id" value="<?= (int)($lesson['id'] ?? 0) ?>">

      <div class="grid" style="grid-template-columns:1fr 1fr; gap:14px">
        <div>
          <label class="muted">Topic</label>
          <select class="input" name="topic" id="topic">
            <option value="">Select topic...</option>
            <?php foreach($topics as $tpc): ?>
              <option value="<?=e($tpc)?>" <?=((string)($lesson['topic'] ?? '')===$tpc?'selected':'')?> ><?=e($tpc)?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label class="muted">Level</label>
          <select class="input" name="level" id="level">
            <?php foreach(['A1','A2','B1','B2','C1'] as $lv): ?>
              <option value="<?=$lv?>" <?=((string)($lesson['level'] ?? 'A1')===$lv?'selected':'')?> ><?=$lv?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label class="muted">Skill</label>
          <select class="input" name="skill" id="skill">
            <?php foreach(['reading','listening','writing','speaking','vocabulary','grammar'] as $sk): ?>
              <option value="<?=$sk?>" <?=((string)($lesson['skill'] ?? 'reading')===$sk?'selected':'')?> ><?=$sk?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label class="muted">Material type</label>
          <select class="input" name="material_type" id="material_type">
            <?php foreach(['text','video','audio','pdf','link'] as $mt): ?>
              <option value="<?=$mt?>" <?=((string)($lesson['material_type'] ?? 'text')===$mt?'selected':'')?> ><?=$mt?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label class="muted">Difficulty (1-5)</label>
          <input class="input" type="number" min="1" max="5" name="difficulty" id="difficulty" value="<?= (int)($lesson['difficulty'] ?? 1) ?>">
        </div>

        <div>
          <label class="muted">Active</label>
          <select class="input" name="is_active" id="is_active">
            <option value="1" <?=((int)($lesson['is_active'] ?? 1)===1?'selected':'')?> >yes</option>
            <option value="0" <?=((int)($lesson['is_active'] ?? 1)===0?'selected':'')?> >no</option>
          </select>
        </div>
      </div>

      <div class="hr"></div>

      <label class="muted">Title</label>
      <input class="input" name="title" id="title" value="<?=e($lesson['title'] ?? '')?>" placeholder="e.g., Past Tense Basics" required>

      <div class="row" style="gap:10px; margin-top:12px; flex-wrap:wrap">
        <button type="button" class="btn" id="aiBtn" onclick="aiGenerateLesson()">Generate with Gemini</button>
        <span class="muted" id="aiStatus"></span>
      </div>

      <div style="margin-top:12px">
        <label class="muted">Lesson content (HTML)</label>
        <textarea class="input" name="body_html" id="body_html" rows="12" placeholder="Generated content will appear here..." required><?=e($lesson['body_html'] ?? '')?></textarea>
      </div>

      <div class="row" style="margin-top:12px; justify-content:space-between; gap:10px; flex-wrap:wrap">
        <button class="btn primary"><?= $editing ? 'Save changes' : 'Add lesson' ?></button>
        <?php if($editing): ?>
          <a class="btn" href="<?=BASE_URL?>/admin/lessons.php">Cancel</a>
        <?php endif; ?>
      </div>
    </form>

    <?php if($editing): ?>
      <div class="hr"></div>
      <form method="post" onsubmit="return confirm('Delete this lesson?')">
        <input type="hidden" name="csrf" value="<?=csrf_token()?>">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" value="<?= (int)$lesson['id'] ?>">
        <button class="btn" style="background:transparent">Delete</button>
      </form>
    <?php endif; ?>
  </div>

  <div class="card" style="margin-top:18px">
    <div class="h1">All lessons</div>
    <div class="muted">Latest 200 lessons.</div>
    <div class="hr"></div>

    <form method="get" class="filter-bar" style="margin-bottom:12px">
      <div class="grid" style="grid-template-columns: 220px 1fr auto auto; gap:12px; align-items:end">
        <div>
          <label class="muted">Level</label>
          <select class="input" name="lv">
            <option value="">All levels</option>
            <?php foreach(['A1','A2','B1','B2','C1'] as $lv): ?>
              <option value="<?=$lv?>" <?=($fLevel===$lv?'selected':'')?> ><?=$lv?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="muted" style="padding-bottom:10px">Tip: Filter lessons by CEFR level.</div>
        <button class="btn primary" type="submit">Apply</button>
        <a class="btn" href="<?=BASE_URL?>/admin/lessons.php">Reset</a>
      </div>
    </form>

    <div class="table-scroll">
      <table class="table" style="min-width:900px">
        <thead>
          <tr>
            <th>ID</th>
            <th>Title</th>
            <th>Topic</th>
            <th>Level</th>
            <th>Skill</th>
            <th>Type</th>
            <th>Diff</th>
            <th>Active</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach($list as $r): ?>
          <tr>
            <td><?= (int)$r['id'] ?></td>
            <td><?=e($r['title'])?></td>
            <td><?=e($r['topic'])?></td>
            <td><?=e($r['level'])?></td>
            <td><?=e($r['skill'])?></td>
            <td><?=e($r['material_type'])?></td>
            <td><?= (int)$r['difficulty'] ?></td>
            <td><?= ((int)$r['is_active']===1?'yes':'no') ?></td>
            <td><a class="btn" href="<?=BASE_URL?>/admin/lessons.php?edit=<?= (int)$r['id'] ?>">Edit</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
const BASE_URL = <?= json_encode(BASE_URL) ?>;

async function aiGenerateLesson(){
  const topic = document.getElementById('topic').value;
  const level = document.getElementById('level').value;
  const skill = document.getElementById('skill').value;
  const material = document.getElementById('material_type').value;
  const difficulty = document.getElementById('difficulty').value;
  const csrf = document.querySelector('input[name="csrf"]').value;

  const status = document.getElementById('aiStatus');
  const btn = document.getElementById('aiBtn');

  if(!topic){
    alert('Please select a topic from the list.');
    return;
  }

  status.textContent = 'Generating...';
  btn.disabled = true;

  try{
    const res = await fetch(`${BASE_URL}/admin/api/ai_generate_lesson.php`,{
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body:`csrf=${encodeURIComponent(csrf)}&topic=${encodeURIComponent(topic)}&level=${encodeURIComponent(level)}&skill=${encodeURIComponent(skill)}&material_type=${encodeURIComponent(material)}&difficulty=${encodeURIComponent(difficulty)}`
    });
    const data = await res.json();
    if(!data.ok){ throw new Error(data.error || 'AI error'); }

    if(data.title){ document.getElementById('title').value = data.title; }
    if(data.body_html){ document.getElementById('body_html').value = data.body_html; }
    status.textContent = 'Done ✓';
  } catch(e){
    status.textContent = 'AI error.';
    alert(e.message || 'AI error');
  } finally {
    btn.disabled = false;
  }
}
</script>
</body>
</html>
