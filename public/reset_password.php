<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/password_reset.php';

start_session();

$token = $_GET['token'] ?? '';
$row = null;
if ($token) {
  $row = verify_password_reset_token($token);
}

$err = '';
$ok = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $token = $_POST['token'] ?? '';
  $new1 = $_POST['password'] ?? '';
  $new2 = $_POST['password2'] ?? '';

  $row = verify_password_reset_token($token);
  if (!$row) {
    $err = "This reset link is invalid or expired.";
  } elseif (strlen($new1) < 6) {
    $err = "Password must be at least 6 characters.";
  } elseif (!hash_equals($new1, $new2)) {
    $err = "Passwords do not match.";
  } else {
    try {
      update_user_password((int)$row['user_id'], $new1);
      consume_password_reset((int)$row['reset_id']);
      $ok = "Your password has been updated. You can now log in.";
    } catch (Throwable $e) {
      error_log("Reset password error: " . $e->getMessage());
      $err = "Something went wrong. Please try again.";
    }
  }
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Reset Password</title>
  <link rel="stylesheet" href="<?=BASE_URL?>/public/assets/css/app.css">
</head>
<body data-theme="dark">
<div class="container">
  <div class="nav">
    <div class="brand"><div class="logo"></div> Reset Password</div>
    <div class="nav-right">
      <a class="btn" href="<?=BASE_URL?>/public/index.php">Back to Login</a>
    </div>
  </div>

  <div class="grid single">
    <div class="card">
      <div class="h1">Set a new password</div>
      <div class="muted">Choose a new password for your account.</div>
      <div class="hr"></div>

      <?php if($err): ?>
        <div class="toast"><?=htmlspecialchars($err)?></div>
        <div class="hr"></div>
      <?php endif; ?>

      <?php if($ok): ?>
        <div class="toast"><?=htmlspecialchars($ok)?></div>
        <div class="hr"></div>
        <a class="btn primary" href="<?=BASE_URL?>/public/index.php" style="width:100%">Go to Login</a>
      <?php elseif(!$row): ?>
        <div class="toast">This reset link is invalid or expired.</div>
        <div class="hr"></div>
        <a class="btn" href="<?=BASE_URL?>/public/forgot_password.php" style="width:100%">Request a new link</a>
      <?php else: ?>
        <form method="post">
          <input type="hidden" name="csrf" value="<?=csrf_token()?>">
          <input type="hidden" name="token" value="<?=htmlspecialchars($token, ENT_QUOTES)?>">
          <input class="input" type="password" name="password" placeholder="New password (min 6)" required><br><br>
          <input class="input" type="password" name="password2" placeholder="Repeat new password" required><br><br>
          <button class="btn primary" style="width:100%">Update password</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>
</body>
</html>
