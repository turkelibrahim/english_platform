<?php
require_once __DIR__ . '/../includes/rbac.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/ai_mode.php';

$u = require_role('student');

$ok = $err = '';

if($_SERVER['REQUEST_METHOD']==='POST'){
  csrf_check();
  $action = $_POST['action'] ?? '';

  if($action === 'update_profile'){
    $full = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');

    // Avatar is a local file upload (optional)
    $avatar = is_string($u['avatar_url'] ?? null) ? trim((string)$u['avatar_url']) : '';
    if (!empty($_FILES['avatar_file']) && isset($_FILES['avatar_file']['error'])) {
      require_once __DIR__ . '/../includes/avatar.php';
      $newRel = avatar_save_upload((int)$u['id'], $_FILES['avatar_file'], $avatar ?: null);
      if ($newRel !== '') $avatar = $newRel;
    }

    $theme = ($_POST['theme'] ?? 'light') === 'dark' ? 'dark' : 'light';

    if($full==='' || $email==='' || $username===''){
      $err = 'Full name, username and email are required.';
    } else {
      try{
        $username = trim(function_exists('mb_strtolower') ? mb_strtolower($username) : strtolower($username));
        if ($username === '' || !preg_match('/^[a-z0-9_\.]{3,60}$/', $username)) {
          throw new Exception('INVALID_USERNAME');
        }

        // Uniqueness checks
        $chkU = db()->prepare("SELECT id FROM users WHERE username=? AND id<>? LIMIT 1");
        $chkU->execute([$username, $u['id']]);
        if ($chkU->fetch()) throw new Exception('USERNAME_EXISTS');

        $chkE = db()->prepare("SELECT id FROM users WHERE email=? AND id<>? LIMIT 1");
        $chkE->execute([$email, $u['id']]);
        if ($chkE->fetch()) throw new Exception('EMAIL_EXISTS');

        db()->prepare("UPDATE users SET full_name=?, username=?, email=?, avatar_url=?, theme=? WHERE id=? AND role='student'")
          ->execute([$full, $username, $email, ($avatar===''?null:$avatar), $theme, $u['id']]);
        $ok = 'Profile updated.';
      }catch(Exception $e){
        $msg = $e->getMessage();
        if ($msg === 'EMAIL_EXISTS') $err = 'This email is already used by another account.';
        elseif ($msg === 'USERNAME_EXISTS') $err = 'This username is already taken.';
        elseif ($msg === 'INVALID_USERNAME') $err = 'Username must be 3-60 chars and contain only letters, numbers, underscore, or dot.';
        else $err = 'Could not update profile.';
      }
    }
  }

  if($action === 'change_password'){
    $cur = (string)($_POST['current_password'] ?? '');
    $new = (string)($_POST['new_password'] ?? '');
    $new2= (string)($_POST['new_password2'] ?? '');

    if(strlen($new) < 6){
      $err = 'New password must be at least 6 characters.';
    } elseif($new !== $new2){
      $err = 'New passwords do not match.';
    } else {
      $st = db()->prepare("SELECT password_hash FROM users WHERE id=? AND role='student'");
      $st->execute([$u['id']]);
      $hash = (string)$st->fetchColumn();
      if(!$hash || !password_verify($cur, $hash)){
        $err = 'Current password is incorrect.';
      } else {
        $nh = password_hash($new, PASSWORD_DEFAULT);
        db()->prepare("UPDATE users SET password_hash=? WHERE id=? AND role='student'")->execute([$nh, $u['id']]);
        $ok = 'Password updated.';
      }
    }
  }

  if($action === 'recalc_mode'){
    update_user_preferred_mode((int)$u['id']);
    $ok = 'AI learning mode recalculated.';
  }

  // refresh user revealed data
  $u = require_role('student');
}

$stats = mode_stats((int)$u['id'], 60);
$decided = decide_preferred_mode($stats, 5, 0.10);
$storedMode = (string)($u['preferred_mode'] ?? 'balanced');


// Last finished tests (reports)
$taskReports = [];
try {
  $trSt = db()->prepare(
    "SELECT tr.*, ut.title, ut.topic
     FROM task_results tr
     JOIN user_tasks ut ON ut.id=tr.task_id
     WHERE tr.user_id=?
     ORDER BY tr.created_at DESC
     LIMIT 15"
  );
  $trSt->execute([$u['id']]);
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
  <title>Student · Profile</title>
  <link rel="stylesheet" href="<?=BASE_URL?>/public/assets/css/app.css">
</head>
<body data-theme="<?=htmlspecialchars($u['theme'] ?? 'light')?>">
<div class="container">
  <?php $navPage="Profile"; $navActive="profile"; include __DIR__ . '/../includes/partials/student_nav.php'; ?>

  <div class="grid" style="margin-top:18px">
    <div class="card">
      <div class="h1">Edit profile</div>
      <div class="hr"></div>

      <?php if($err): ?><div class="toast"><?=htmlspecialchars($err)?></div><div class="hr"></div><?php endif; ?>
      <?php if($ok): ?><div class="toast"><?=htmlspecialchars($ok)?></div><div class="hr"></div><?php endif; ?>

      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?=csrf_token()?>">
        <input type="hidden" name="action" value="update_profile">

        <label class="muted">Full name</label>
        <input class="input" name="full_name" value="<?=htmlspecialchars($u['full_name'] ?? '')?>" required><br><br>

        <label class="muted">Username (unique)</label>
        <input class="input" name="username" value="<?=htmlspecialchars($u['username'] ?? '')?>" required><br><br>

        <label class="muted">Email</label>
        <input class="input" type="email" name="email" value="<?=htmlspecialchars($u['email'] ?? '')?>" required><br><br>

        <label class="muted">Profile photo (optional)</label>
        <input class="input" type="file" name="avatar_file" accept="image/*"><br><br>

        <label class="muted">Theme</label>
        <select class="input" name="theme">
          <option value="light" <?=($u['theme'] ?? 'light')==='light'?'selected':''?>>light</option>
          <option value="dark" <?=($u['theme'] ?? 'light')==='dark'?'selected':''?>>dark</option>
        </select><br><br>

        <button class="btn primary" style="width:100%">Save changes</button>
      </form>

      <div class="hr"></div>
      <div class="h1" style="font-size:18px">Change password</div>
      <div class="hr"></div>

      <form method="post">
        <input type="hidden" name="csrf" value="<?=csrf_token()?>">
        <input type="hidden" name="action" value="change_password">

        <input class="input" type="password" name="current_password" placeholder="Current password" required><br><br>
        <input class="input" type="password" name="new_password" placeholder="New password" required><br><br>
        <input class="input" type="password" name="new_password2" placeholder="New password (again)" required><br><br>

        <button class="btn" style="width:100%">Update password</button>
      </form>
    </div>

    <div class="card">
      <div class="h1">Learning mode</div>
      <div class="muted" style="font-size:13px">
        Based on your <b>Task</b> + <b>Lesson quiz</b> results (last 60 days).<br>
        Rule: <b>mp3</b> question = <b>audio</b>, otherwise <b>reading</b>.
      </div>
      <div class="hr"></div>

      <div style="line-height:1.9">
        Reading accuracy: <b><?=pct($stats['reading']['acc'])?></b> (<?= (int)$stats['reading']['correct'] ?>/<?= (int)$stats['reading']['total'] ?>)<br>
        Audio accuracy: <b><?=pct($stats['audio']['acc'])?></b> (<?= (int)$stats['audio']['correct'] ?>/<?= (int)$stats['audio']['total'] ?>)
      </div>

      <div class="hr"></div>
      <div style="line-height:1.8">
        Stored preferred_mode: <b><?=htmlspecialchars($storedMode)?></b><br>
        AI decision right now: <b><?=htmlspecialchars($decided)?></b>
      </div>

      <div style="margin-top:12px">
        <form method="post">
          <input type="hidden" name="csrf" value="<?=csrf_token()?>">
          <input type="hidden" name="action" value="recalc_mode">
          <button class="btn">Recalculate</button>
        </form>
      </div>
    </div>
  </div>

  <div class="card" style="margin-top:18px">
    <div class="h1">Test reports</div>
    <div class="muted">Last finished practice tests (saved in your profile).</div>
    <div class="hr"></div>

    <?php if(!$taskReports): ?>
      <div class="toast">No finished tests yet.</div>
    <?php else: ?>
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
  </div>


</div>
</body>
</html>
