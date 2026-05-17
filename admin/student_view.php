<?php
require_once __DIR__ . '/../includes/rbac.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/ai_mode.php';
require_once __DIR__ . '/../includes/avatar.php';

$u = require_role('admin');
$sid = (int)($_GET['id'] ?? 0);
$username = trim($_GET['username'] ?? '');
if(!$sid && $username===''){ exit('Missing id'); }

$st = db()->prepare("SELECT id, role, username, full_name, email, avatar_url, level, placement_completed, theme, points, streak, last_active_at, created_at, preferred_mode, preferred_mode_updated_at
                    FROM users WHERE ".($username!=='' ? "username=?" : "id=?")." LIMIT 1");
$st->execute([$username!=='' ? $username : $sid]);
$student = $st->fetch();
if(!$student || $student['role'] !== 'student'){
  exit('Student not found');
}

$avatarRel = is_string($student['avatar_url'] ?? null) ? trim((string)$student['avatar_url']) : '';
$avatarSrc = $avatarRel !== '' ? avatar_public_url($avatarRel) : '';

$stats = mode_stats((int)$student['id'], 60);
$decided = decide_preferred_mode($stats, 5, 0.10);

// Optional: admin can force recompute stored mode
$ok = '';
$err = '';
if($_SERVER['REQUEST_METHOD'] === 'POST'){
  csrf_check();
  $action = $_POST['action'] ?? '';
  if($action === 'set_level'){
    $level = trim($_POST['level'] ?? '');
    $placementDone = (int)($_POST['placement_completed'] ?? 0);
    db()->prepare("UPDATE users SET level=?, placement_completed=? WHERE id=? AND role='student'")
      ->execute([$level?:null, $placementDone, (int)$student['id']]);
    $ok = 'Student updated.';

    // Refresh student data
    $st = db()->prepare("SELECT id, role, username, full_name, email, avatar_url, level, placement_completed, theme, points, streak, last_active_at, created_at, preferred_mode, preferred_mode_updated_at
                        FROM users WHERE id=? LIMIT 1");
    $st->execute([(int)$student['id']]);
    $student = $st->fetch();
    $avatarRel = is_string($student['avatar_url'] ?? null) ? trim((string)$student['avatar_url']) : '';
    $avatarSrc = $avatarRel !== '' ? avatar_public_url($avatarRel) : '';
  }

  if($action === 'recalc_mode'){
    $new = update_user_preferred_mode((int)$student['id']);
    $student['preferred_mode'] = $new;
    $student['preferred_mode_updated_at'] = date('Y-m-d H:i:s');
    $ok = 'Preferred mode recalculated.';
  }
}


// Last finished tests (reports)
$taskReports = [];
try {
  $trSt = db()->prepare(
    "SELECT tr.*, ut.title, ut.topic\n     FROM task_results tr\n     JOIN user_tasks ut ON ut.id=tr.task_id\n     WHERE tr.user_id=?\n     ORDER BY tr.created_at DESC\n     LIMIT 15"
  );
  $trSt->execute([(int)$student['id']]);
  $taskReports = $trSt->fetchAll();
} catch (Throwable $e) {
  $taskReports = [];
}

function pct($x){
  if($x === null) return '-';
  return (string)round($x*100, 1).'%';
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Admin · Student Profile</title>
  <link rel="stylesheet" href="<?=BASE_URL?>/public/assets/css/app.css">
</head>
<body data-theme="<?=htmlspecialchars($u['theme'] ?? 'light')?>">
<div class="container">
  <?php $navPage="Student"; $navActive="students"; include __DIR__ . '/../includes/partials/admin_nav.php'; ?>

  <div class="card" style="margin-top:18px">
    <div class="h1"><?=htmlspecialchars($student['full_name'])?></div>
    <div class="muted">@<?=htmlspecialchars($student['username'] ?? '')?> · <?=htmlspecialchars($student['email'])?></div>

    <?php if($avatarSrc): ?>
      <div style="margin-top:12px">
        <img src="<?=htmlspecialchars($avatarSrc, ENT_QUOTES)?>" alt="avatar" style="width:84px;height:84px;border-radius:18px;object-fit:cover">
      </div>
    <?php endif; ?>

    <?php if($ok): ?><div class="hr"></div><div class="toast"><?=htmlspecialchars($ok)?></div><?php endif; ?>

    <div class="hr"></div>

    <div class="grid" style="grid-template-columns:1fr 1fr; gap:14px">
      <div class="card" style="box-shadow:none">
        <div class="muted">Account</div>
        <div style="margin-top:8px">
          Level: <b><?=htmlspecialchars($student['level'] ?? '-')?></b><br>
          Placement completed: <b><?= ((int)$student['placement_completed']===1) ? 'yes' : 'no' ?></b><br>
          Points: <b><?= (int)$student['points'] ?></b><br>
          Streak: <b><?= (int)$student['streak'] ?></b><br>
          Theme: <b><?=htmlspecialchars($student['theme'] ?? 'light')?></b><br>
          Last active: <b><?=htmlspecialchars($student['last_active_at'] ?? '-')?></b><br>
          Created at: <b><?=htmlspecialchars($student['created_at'] ?? '-')?></b>
        </div>

        <div class="hr"></div>
        <div class="muted">Update student</div>
        <form method="post" style="margin-top:10px">
          <input type="hidden" name="csrf" value="<?=csrf_token()?>">
          <input type="hidden" name="action" value="set_level">
          <div class="row" style="gap:10px; flex-wrap:wrap">
            <select class="input" name="level" style="max-width:140px">
              <option value="" <?=empty($student['level'])?'selected':''?>>(no level)</option>
              <?php foreach(['A1','A2','B1','B2','C1'] as $lv): ?>
                <option value="<?=$lv?>" <?=((string)$student['level']===$lv?'selected':'')?>><?=$lv?></option>
              <?php endforeach; ?>
            </select>
            <select class="input" name="placement_completed" style="max-width:260px">
              <option value="0" <?=((int)$student['placement_completed']===0?'selected':'')?>>Placement required</option>
              <option value="1" <?=((int)$student['placement_completed']===1?'selected':'')?>>Placement completed</option>
            </select>
            <button class="btn">Update</button>
          </div>
        </form>
      </div>

      <div class="card" style="box-shadow:none">
        <div class="muted">AI learning mode</div>
        <div style="margin-top:8px">
          Stored preferred_mode: <b><?=htmlspecialchars($student['preferred_mode'] ?? 'balanced')?></b><br>
          Updated at: <b><?=htmlspecialchars($student['preferred_mode_updated_at'] ?? '-')?></b>
        </div>

        <div class="hr"></div>

        <div class="muted" style="font-size:13px">
          Last 60 days (proxy):
        </div>
        <div style="margin-top:8px">
          Reading accuracy: <b><?=pct($stats['reading']['acc'])?></b> (<?= (int)$stats['reading']['correct'] ?>/<?= (int)$stats['reading']['total'] ?>)
          <br>
          Audio accuracy: <b><?=pct($stats['audio']['acc'])?></b> (<?= (int)$stats['audio']['correct'] ?>/<?= (int)$stats['audio']['total'] ?>)
          <br>
          AI decision right now: <b><?=htmlspecialchars($decided)?></b>
        </div>

        <div style="margin-top:12px">
          <form method="post">
            <input type="hidden" name="csrf" value="<?=csrf_token()?>">
            <input type="hidden" name="action" value="recalc_mode">
            <button class="btn">Recalculate stored mode</button>
          </form>
        </div>
      </div>
    </div>

    <div class="hr"></div>

    <div class="h1" style="font-size:18px">Finished tests</div>
    <div class="muted">Last finished practice tests for this student.</div>

    <?php if(!$taskReports): ?>
      <div class="hr"></div>
      <div class="toast">No finished tests yet.</div>
    <?php else: ?>
      <div class="hr"></div>
      <div style="overflow:auto">
        <table class="table" style="min-width:720px">
          <thead>
            <tr>
              <th>Date</th>
              <th>Title</th>
              <th>Topic</th>
              <th>Correct</th>
              <th>Wrong</th>
              <th>Score</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach($taskReports as $r): ?>
              <tr>
                <td><?=htmlspecialchars($r['created_at'])?></td>
                <td><?=htmlspecialchars($r['title'] ?? '')?></td>
                <td><?=htmlspecialchars($r['topic'] ?? '')?></td>
                <td><b><?= (int)$r['correct_count'] ?></b></td>
                <td><b><?= (int)$r['wrong_count'] ?></b></td>
                <td><b><?=htmlspecialchars($r['score_pct'])?>%</b></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

    <div class="hr"></div>
    <a class="btn" href="<?=BASE_URL?>/admin/students.php">← Back to Students</a>
  </div>
</div>
</body>
</html>
