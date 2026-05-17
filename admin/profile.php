<?php
require_once __DIR__ . '/../includes/rbac.php';
require_once __DIR__ . '/../includes/csrf.php';

$u = require_role('admin');
$ok = $err = '';

if($_SERVER['REQUEST_METHOD']==='POST'){
  csrf_check();
  $action = $_POST['action'] ?? '';

  if($action === 'update_profile'){
    $full = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    // Avatar is now a local file upload (optional)
    $avatar = is_string($u['avatar_url'] ?? null) ? trim((string)$u['avatar_url']) : '';
    if (!empty($_FILES['avatar_file']) && isset($_FILES['avatar_file']['error'])) {
      require_once __DIR__ . '/../includes/avatar.php';
      $newRel = avatar_save_upload((int)$u['id'], $_FILES['avatar_file'], $avatar ?: null);
      if ($newRel !== '') $avatar = $newRel;
    }
    $theme = ($_POST['theme'] ?? 'light') === 'dark' ? 'dark' : 'light';

    if($full==='' || $email===''){
      $err = 'Full name and email are required.';
    } else {
      try{
        db()->prepare("UPDATE users SET full_name=?, email=?, avatar_url=?, theme=? WHERE id=? AND role='admin'")
          ->execute([$full, $email, ($avatar===''?null:$avatar), $theme, $u['id']]);
        $ok = 'Profile updated.';
      }catch(Exception $e){
        $err = 'Could not update profile (email may already be used).';
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
      $st = db()->prepare("SELECT password_hash FROM users WHERE id=? AND role='admin'");
      $st->execute([$u['id']]);
      $hash = (string)$st->fetchColumn();
      if(!$hash || !password_verify($cur, $hash)){
        $err = 'Current password is incorrect.';
      } else {
        $nh = password_hash($new, PASSWORD_DEFAULT);
        db()->prepare("UPDATE users SET password_hash=? WHERE id=? AND role='admin'")->execute([$nh, $u['id']]);
        $ok = 'Password updated.';
      }
    }
  }

  $u = require_role('admin');
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Admin · Profile</title>
  <link rel="stylesheet" href="<?=BASE_URL?>/public/assets/css/app.css">
</head>
<body data-theme="<?=htmlspecialchars($u['theme'] ?? 'light')?>">
<div class="container">
  <?php $navPage="Profile"; $navActive="profile"; include __DIR__ . '/../includes/partials/admin_nav.php'; ?>

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
      <div class="h1">Quick info</div>
      <div class="muted">Role: <b>admin</b></div>
      <div class="hr"></div>
      <div style="line-height:1.9">
        Last active: <b><?=htmlspecialchars($u['last_active_at'] ?? '-')?></b><br>
        Created at: <b><?=htmlspecialchars($u['created_at'] ?? '-')?></b>
      </div>
    </div>
  </div>
</div>
</body>
</html>
