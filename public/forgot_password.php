<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/config.php';

// Local MVP: password reset happens directly on this page (no email sending).

start_session();
$u = current_user();
if ($u) {
  if (($u['role'] ?? '') === 'admin') header('Location: '.BASE_URL.'/admin/dashboard.php');
  else {
    if ((int)($u['placement_completed'] ?? 0) === 0) header('Location: '.BASE_URL.'/student/placement.php');
    else header('Location: '.BASE_URL.'/student/dashboard.php');
  }
  exit;
}

$err = '';
$ok  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $email = trim((string)($_POST['email'] ?? ''));
  $p1 = (string)($_POST['new_password'] ?? '');
  $p2 = (string)($_POST['new_password2'] ?? '');

  if ($email === '') {
    $err = 'Email is required.';
  } elseif (strlen($p1) < 6) {
    $err = 'New password must be at least 6 characters.';
  } elseif ($p1 !== $p2) {
    $err = 'Passwords do not match.';
  } else {
    try {
      // Only allow student accounts to reset via Student portal.
      $st = db()->prepare("SELECT id, role FROM users WHERE email=? LIMIT 1");
      $st->execute([$email]);
      $row = $st->fetch();
      if ($row && ($row['role'] ?? '') === 'student') {
        $nh = password_hash($p1, PASSWORD_DEFAULT);
        db()->prepare("UPDATE users SET password_hash=? WHERE id=? AND role='student'")->execute([$nh, (int)$row['id']]);
      }
      // Always show a generic success message (avoid account enumeration).
      $ok = 'If that email exists, the password has been updated. You can now log in.';
    } catch (Exception $e) {
      error_log('Forgot password error: '.$e->getMessage());
      $err = 'Could not reset password. Please try again.';
    }
  }
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Password Reset</title>
  <link rel="stylesheet" href="<?=BASE_URL?>/public/assets/css/app.css">
</head>
<body data-theme="dark">
<div class="container" style="max-width:900px">
  <div class="card" style="margin-top:24px">
    <div class="row" style="justify-content:space-between; align-items:center">
      <div class="brand"><div class="logo"></div> Password Reset</div>
      <a class="btn" href="<?=BASE_URL?>/public/index.php">Back to Login</a>
    </div>
    <div class="hr"></div>

    <div class="h1">Forgot your password?</div>
    <div class="muted">Enter your email and choose a new password.</div>
    <div class="hr"></div>

    <?php if($err): ?>
      <div class="toast"><?=htmlspecialchars($err)?></div>
      <div class="hr"></div>
    <?php endif; ?>

    <?php if($ok): ?>
      <div class="toast"><?=htmlspecialchars($ok)?></div>
      <div class="hr"></div>
    <?php endif; ?>

    <form method="post">
      <input type="hidden" name="csrf" value="<?=csrf_token()?>">
      <input class="input" type="email" name="email" placeholder="Email" required><br><br>
      <input class="input" type="password" name="new_password" placeholder="New password (min 6)" required><br><br>
      <input class="input" type="password" name="new_password2" placeholder="New password (again)" required><br><br>
      <button class="btn primary" style="width:100%">Reset password</button>
    </form>
  </div>
</div>
</body>
</html>
